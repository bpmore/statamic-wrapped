<?php

use Bpmore\Wrapped\Export\FfmpegRenderer;
use Bpmore\Wrapped\Export\VideoSpec;

/**
 * A tiny valid PNG, so FFmpeg has a real image to work from.
 */
function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
}

function withRealFfmpeg(): FfmpegRenderer
{
    $renderer = new FfmpegRenderer;

    if (! $renderer->isAvailable()) {
        test()->markTestSkipped('No FFmpeg on this machine.');
    }

    return $renderer;
}

/**
 * Seconds, read straight from the MP4's mvhd box rather than via ffprobe, so
 * the test does not depend on a second binary.
 */
function mp4Duration(string $mp4): float
{
    $at = strpos($mp4, 'mvhd');

    expect($at)->not->toBeFalse('no mvhd box: not a valid MP4');

    // Version 0 mvhd: 4 bytes flags, 4 created, 4 modified, 4 timescale, 4 duration.
    $timescale = unpack('N', substr($mp4, $at + 16, 4))[1];
    $duration = unpack('N', substr($mp4, $at + 20, 4))[1];

    return $duration / $timescale;
}

describe('the spec', function () {
    it('works out the running time with the overlaps taken off', function () {
        $spec = new VideoSpec(secondsPerFrame: 3.0, crossfade: 0.6);

        expect($spec->duration(0))->toBe(0.0)
            ->and($spec->duration(1))->toBe(3.0)
            ->and($spec->duration(3))->toBe(7.8)
            ->and($spec->duration(10))->toBe(24.6);
    });

    it('is portrait at phone size by default', function () {
        $spec = new VideoSpec;

        expect($spec->width)->toBe(1080)->and($spec->height)->toBe(1920)->and($spec->fps)->toBe(30);
    });
});

describe('finding ffmpeg', function () {
    it('is unavailable when the configured binary is not there', function () {
        config(['wrapped.video.ffmpeg' => '/definitely/not/ffmpeg']);

        expect((new FfmpegRenderer)->isAvailable())->toBeFalse();
    });

    it('says what to do when it cannot find one', function () {
        config(['wrapped.video.ffmpeg' => '/definitely/not/ffmpeg']);

        expect(fn () => (new FfmpegRenderer)->render([tinyPng()], null, new VideoSpec))
            ->toThrow(RuntimeException::class, 'wrapped.video.ffmpeg');
    });
});

describe('refusing bad input before touching ffmpeg', function () {
    it('needs at least one frame', function () {
        expect(fn () => (new FfmpegRenderer)->render([], null, new VideoSpec))
            ->toThrow(RuntimeException::class, 'at least one frame');
    });

    it('refuses a soundtrack that does not exist', function () {
        expect(fn () => withRealFfmpeg()->render([tinyPng()], '/nope/track.mp3', new VideoSpec))
            ->toThrow(RuntimeException::class, 'does not exist');
    });
});

describe('with real ffmpeg', function () {
    it('produces a playable mp4 of the right length, silent', function () {
        $spec = new VideoSpec(width: 108, height: 192, secondsPerFrame: 1.0, crossfade: 0.2, fps: 10);

        $mp4 = withRealFfmpeg()->render([tinyPng(), tinyPng(), tinyPng()], null, $spec);

        expect(substr($mp4, 4, 4))->toBe('ftyp')
            ->and(mp4Duration($mp4))->toEqualWithDelta($spec->duration(3), 0.15);
    });

    it('handles a single frame with no crossfade to make', function () {
        $spec = new VideoSpec(width: 108, height: 192, secondsPerFrame: 1.0, crossfade: 0.2, fps: 10);

        $mp4 = withRealFfmpeg()->render([tinyPng()], null, $spec);

        expect(mp4Duration($mp4))->toEqualWithDelta(1.0, 0.15);
    });

    it('lays a soundtrack under the frames and stops when they do', function () {
        $renderer = withRealFfmpeg();
        $spec = new VideoSpec(width: 108, height: 192, secondsPerFrame: 1.0, crossfade: 0.2, fps: 10);

        // A ten-second tone, longer than the video, so the trim is exercised.
        $tone = sys_get_temp_dir().'/wrapped-tone-'.bin2hex(random_bytes(4)).'.wav';
        exec(sprintf('%s -hide_banner -loglevel error -y -f lavfi -i "sine=frequency=440:duration=10" %s',
            escapeshellarg(config('wrapped.video.ffmpeg') ?: '/opt/homebrew/bin/ffmpeg'), escapeshellarg($tone)));

        if (! is_file($tone)) {
            test()->markTestSkipped('Could not synthesise a test tone.');
        }

        try {
            $mp4 = $renderer->render([tinyPng(), tinyPng()], $tone, $spec);
        } finally {
            @unlink($tone);
        }

        expect(substr($mp4, 4, 4))->toBe('ftyp')
            ->and(mp4Duration($mp4))->toEqualWithDelta(1.8, 0.15)
            // An audio track is present: AAC's sample description box.
            ->and(strpos($mp4, 'mp4a'))->not->toBeFalse();
    });

    it('cleans up after itself', function () {
        $before = count(glob(sys_get_temp_dir().'/wrapped-video-*') ?: []);

        withRealFfmpeg()->render([tinyPng()], null, new VideoSpec(width: 108, height: 192, secondsPerFrame: 0.5, fps: 10));

        expect(count(glob(sys_get_temp_dir().'/wrapped-video-*') ?: []))->toBe($before);
    });
});
