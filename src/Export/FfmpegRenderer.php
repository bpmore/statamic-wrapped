<?php

namespace Bpmore\Wrapped\Export;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Stitches still frames into an MP4 with FFmpeg.
 *
 * Each frame is held for as long as its text takes to read, with a slow push-in
 * so it does not sit dead on screen, crossfaded into the next, with the footer
 * pinned motionless over all of it and the music faded out under the last one. H.264 and AAC in an MP4 with the index at the front, which is the
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

    public function render(array $frames, ?string $overlay, ?string $audio, VideoSpec $spec): string
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

            foreach ($frames as $i => $frame) {
                $paths[] = $path = sprintf('%s/frame-%03d.png', $directory, $i);
                file_put_contents($path, $frame->png);
            }

            $overlayPath = null;

            if ($overlay !== null) {
                file_put_contents($overlayPath = $directory.'/overlay.png', $overlay);
            }

            $process = new Process(
                $this->command($binary, $paths, $frames, $overlayPath, $audio, $spec, $output),
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

    public function split(string $sheet, int $width, int $height, int $count, int $columns): array
    {
        if ($count < 1) {
            return [];
        }

        $binary = $this->binary();

        if ($binary === null) {
            throw new RuntimeException('No FFmpeg binary was found. Set wrapped.video.ffmpeg to its path.');
        }

        $columns = max(1, $columns);
        $directory = $this->workspace();
        $source = $directory.'/sheet.png';

        try {
            file_put_contents($source, $sheet);

            // One process: fan the sheet out and crop every cell, alpha kept.
            $graph = sprintf('[0:v]format=rgba,split=%d', $count);
            $outputs = [];

            for ($i = 0; $i < $count; $i++) {
                $graph .= "[s{$i}]";
            }

            for ($i = 0; $i < $count; $i++) {
                $x = ($i % $columns) * $width;
                $y = intdiv($i, $columns) * $height;
                $graph .= sprintf(';[s%d]crop=%d:%d:%d:%d[c%d]', $i, $width, $height, $x, $y, $i);
                $outputs[] = sprintf('%s/cell-%03d.png', $directory, $i);
            }

            $command = [$binary, '-hide_banner', '-loglevel', 'error', '-y', '-i', $source, '-filter_complex', $graph];

            foreach ($outputs as $i => $path) {
                array_push($command, '-map', "[c{$i}]", '-frames:v', '1', $path);
            }

            (new Process($command, timeout: $this->timeout))->mustRun();

            $cells = [];

            foreach ($outputs as $path) {
                $bytes = is_file($path) ? file_get_contents($path) : false;

                if ($bytes === false || $bytes === '') {
                    throw new RuntimeException('FFmpeg did not produce every frame from the sheet.');
                }

                $cells[] = $bytes;
            }

            return $cells;
        } catch (ProcessFailedException $e) {
            throw new RuntimeException('FFmpeg failed to slice the sheet: '.$e->getProcess()->getErrorOutput(), previous: $e);
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
     * Each still is one input, looped for its own time on screen. The filter
     * graph scales and pushes in on each, crossfades them in sequence, pins the
     * overlay on top, and fades the audio out under the last frame. Written out
     * rather than built by a library so that what runs is exactly what can be
     * read here.
     *
     * @param  list<string>  $paths  Frame files, in order.
     * @param  list<Frame>  $frames  The same frames, for their durations.
     * @return list<string>
     */
    protected function command(string $binary, array $paths, array $frames, ?string $overlay, ?string $audio, VideoSpec $spec, string $output): array
    {
        $command = [$binary, '-hide_banner', '-loglevel', 'error', '-y'];

        foreach ($paths as $i => $path) {
            array_push($command, '-loop', '1', '-t', (string) $frames[$i]->seconds, '-i', $path);
        }

        if ($overlay !== null) {
            array_push($command, '-i', $overlay);
        }

        if ($audio !== null) {
            array_push($command, '-i', $audio);
        }

        array_push(
            $command,
            '-filter_complex', $this->filterGraph($frames, $overlay !== null, $audio !== null, $spec),
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
            '-t', (string) $spec->duration($frames),
            $output,
        );

        return $command;
    }

    /**
     * @param  list<Frame>  $frames
     */
    protected function filterGraph(array $frames, bool $withOverlay, bool $withAudio, VideoSpec $spec): string
    {
        $count = count($frames);
        $parts = [];

        // Scale each still to size and push in very slightly over its life:
        // a still that does not move at all reads as a slideshow, one that
        // moves too much reads as a screensaver. The push-in is on the frame
        // only; the overlay pinned later never moves.
        foreach ($frames as $i => $frame) {
            $parts[] = sprintf(
                "[%d:v]scale=%d:%d:flags=lanczos,setsar=1,zoompan=z='min(zoom+0.0006,1.06)':d=%d:s=%dx%d:fps=%d,format=yuv420p[f%d]",
                $i, $spec->width, $spec->height, (int) round($frame->seconds * $spec->fps), $spec->width, $spec->height, $spec->fps, $i,
            );
        }

        // Chain the crossfades. Each fade starts where the previous frames'
        // combined time ends, minus one overlap per join so far.
        $joined = $withOverlay ? 'stitched' : 'video';

        if ($count === 1) {
            $parts[] = "[f0]copy[{$joined}]";
        } else {
            $previous = 'f0';
            $elapsed = 0.0;

            for ($i = 1; $i < $count; $i++) {
                $elapsed += $frames[$i - 1]->seconds - $spec->crossfade;
                $label = $i === $count - 1 ? $joined : "x{$i}";

                $parts[] = sprintf(
                    '[%s][f%d]xfade=transition=fade:duration=%s:offset=%s[%s]',
                    $previous, $i, $spec->crossfade, round($elapsed, 3), $label,
                );

                $previous = $label;
            }
        }

        // The overlay input comes straight after the frames.
        if ($withOverlay) {
            $parts[] = sprintf(
                '[%d:v]scale=%d:%d:flags=lanczos,format=rgba[pin];[stitched][pin]overlay=0:0:format=auto[video]',
                $count, $spec->width, $spec->height,
            );
        }

        if ($withAudio) {
            $duration = $spec->duration($frames);
            $fadeOut = max($duration - 2.0, 0);
            $audioInput = $count + ($withOverlay ? 1 : 0);

            // Trim to length, fade in over the intro, out under the outro.
            $parts[] = sprintf(
                '[%d:a]atrim=0:%s,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.8,afade=t=out:st=%s:d=2[audio]',
                $audioInput, $duration, $fadeOut,
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
