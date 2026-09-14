<?php

namespace Bpmore\Wrapped\Export;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Stitches still frames into an MP4 with FFmpeg.
 *
 * Each frame is held for a few seconds with a slow push-in so it does not sit
 * dead on screen, crossfaded into the next, with the music faded out under the
 * last one. H.264 and AAC in an MP4 with the index at the front, which is the
 * one combination every phone and every social platform accepts without
 * re-encoding.
 *
 * FFmpeg is driven directly, like Chrome is. If a site has it, videos work; if
 * not, the video download is unavailable and everything else carries on.
 */
class FfmpegRenderer implements VideoRenderer
{
    protected const LIKELY_PATHS = [
        '/opt/homebrew/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/usr/bin/ffmpeg',
        '/snap/bin/ffmpeg',
    ];

    public function __construct(protected int $timeout = 120) {}

    public function isAvailable(): bool
    {
        return $this->binary() !== null;
    }

    public function render(array $frames, ?string $audio, VideoSpec $spec): string
    {
        if ($frames === []) {
            throw new RuntimeException('A video needs at least one frame.');
        }

        $binary = $this->binary();

        if ($binary === null) {
            throw new RuntimeException('No FFmpeg binary was found. Set wrapped.video.ffmpeg to its path.');
        }

        if ($audio !== null && ! is_file($audio)) {
            throw new RuntimeException("The soundtrack at [{$audio}] does not exist.");
        }

        $directory = $this->workspace();
        $output = $directory.'/wrapped.mp4';

        try {
            $paths = [];

            foreach ($frames as $i => $png) {
                $paths[] = $path = sprintf('%s/frame-%03d.png', $directory, $i);
                file_put_contents($path, $png);
            }

            $process = new Process(
                $this->command($binary, $paths, $audio, $spec, $output),
                timeout: $this->timeout,
            );

            $process->mustRun();

            $bytes = is_file($output) ? file_get_contents($output) : false;

            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('FFmpeg ran but produced no video.');
            }

            return $bytes;
        } catch (ProcessFailedException $e) {
            throw new RuntimeException('FFmpeg failed to render the video: '.$e->getProcess()->getErrorOutput(), previous: $e);
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }

    /**
     * The full FFmpeg invocation.
     *
     * Each still is one input, looped for its on-screen time. The filter graph
     * scales and pushes in on each, crossfades them in sequence, and fades the
     * audio out under the last frame. Written out rather than built by a
     * library so that what runs is exactly what can be read here.
     *
     * @param  list<string>  $frames
     * @return list<string>
     */
    protected function command(string $binary, array $frames, ?string $audio, VideoSpec $spec, string $output): array
    {
        $command = [$binary, '-hide_banner', '-loglevel', 'error', '-y'];

        foreach ($frames as $frame) {
            array_push($command, '-loop', '1', '-t', (string) $spec->secondsPerFrame, '-i', $frame);
        }

        if ($audio !== null) {
            array_push($command, '-i', $audio);
        }

        array_push(
            $command,
            '-filter_complex', $this->filterGraph(count($frames), $audio !== null, $spec),
            '-map', '[video]',
        );

        if ($audio !== null) {
            array_push($command, '-map', '[audio]', '-c:a', 'aac', '-b:a', '160k');
        }

        array_push(
            $command,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '22',
            '-pix_fmt', 'yuv420p',
            '-r', (string) $spec->fps,
            '-movflags', '+faststart',
            '-t', (string) $spec->duration(count($frames)),
            $output,
        );

        return $command;
    }

    protected function filterGraph(int $frames, bool $withAudio, VideoSpec $spec): string
    {
        $perFrame = (int) round($spec->secondsPerFrame * $spec->fps);
        $parts = [];

        // Scale each still to size and push in very slightly over its life:
        // a still that does not move at all reads as a slideshow, one that
        // moves too much reads as a screensaver.
        for ($i = 0; $i < $frames; $i++) {
            $parts[] = sprintf(
                "[%d:v]scale=%d:%d:flags=lanczos,setsar=1,zoompan=z='min(zoom+0.0006,1.06)':d=%d:s=%dx%d:fps=%d,format=yuv420p[f%d]",
                $i, $spec->width, $spec->height, $perFrame, $spec->width, $spec->height, $spec->fps, $i,
            );
        }

        // Chain the crossfades. Each fade starts where the previous frame's
        // time ends, minus the overlap.
        if ($frames === 1) {
            $parts[] = '[f0]copy[video]';
        } else {
            $previous = 'f0';

            for ($i = 1; $i < $frames; $i++) {
                $offset = $i * $spec->secondsPerFrame - $i * $spec->crossfade;
                $label = $i === $frames - 1 ? 'video' : "x{$i}";

                $parts[] = sprintf(
                    '[%s][f%d]xfade=transition=fade:duration=%s:offset=%s[%s]',
                    $previous, $i, $spec->crossfade, $offset, $label,
                );

                $previous = $label;
            }
        }

        if ($withAudio) {
            $duration = $spec->duration($frames);
            $fadeOut = max($duration - 2.0, 0);

            // Trim to length, fade in over the intro, out under the outro.
            $parts[] = sprintf(
                '[%d:a]atrim=0:%s,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.8,afade=t=out:st=%s:d=2[audio]',
                $frames, $duration, $fadeOut,
            );
        }

        return implode(';', $parts);
    }

    protected function binary(): ?string
    {
        $configured = config('wrapped.video.ffmpeg');

        if (is_string($configured) && $configured !== '') {
            return is_executable($configured) ? $configured : null;
        }

        foreach (self::LIKELY_PATHS as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function workspace(): string
    {
        $directory = sys_get_temp_dir().'/wrapped-video-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create a temporary directory to render into.');
        }

        return $directory;
    }
}
