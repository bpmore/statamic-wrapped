<?php

use Bpmore\Wrapped\Export\FfmpegRenderer;
use Bpmore\Wrapped\Export\Frame;
use Bpmore\Wrapped\Export\VideoSpec;

/**
 * A tiny valid PNG, so FFmpeg has a real image to work from.
 */
function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
}

function frame(float $seconds = 1.0): Frame
{
    return new Frame(tinyPng(), $seconds);
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
        $spec = new VideoSpec(crossfade: 0.6);

        expect($spec->duration([]))->toBe(0.0)
            ->and($spec->duration([3.0]))->toBe(3.0)
            ->and($spec->duration([3.0, 3.0, 3.0]))->toBe(7.8)
            ->and($spec->duration([frame(4.0), frame(6.0)]))->toBe(9.4);
    });

    it('gives a reader time in proportion to the words on screen', function () {
        $spec = new VideoSpec;

        expect($spec->secondsFor('12 files.'))->toBe(4.0)
            // 4 words: 1.5 + 1.6 is under the floor, so the floor.
            ->and($spec->secondsFor('You published 47 entries, against 21 the period before.'))->toBe(5.1)
            // 9 words: 1.5 + 3.6.
            ->and($spec->secondsFor(str_repeat('word ', 40)))->toBe(9.0);
        // 40 words would be 17.5s; capped, because a wall of text is a design bug.
    });

    it('never rushes a short card below the floor', function () {
        expect((new VideoSpec)->secondsFor('Hi'))->toBe(4.0)
            ->and((new VideoSpec)->secondsFor(''))->toBe(4.0);
    });

    it('is portrait at phone size by default, with a gentle crossfade', function () {
        $spec = new VideoSpec;

        expect($spec->width)->toBe(1080)->and($spec->height)->toBe(1920)->and($spec->fps)->toBe(30)
            ->and($spec->crossfade)->toBe(0.8);
    });
});

describe('finding ffmpeg', function () {
    it('is unavailable when the configured binary is not there', function () {
        config(['wrapped.video.ffmpeg' => '/definitely/not/ffmpeg']);

        expect((new FfmpegRenderer)->isAvailable())->toBeFalse();
    });

    it('says what to do when it cannot find one', function () {
        config(['wrapped.video.ffmpeg' => '/definitely/not/ffmpeg']);

        expect(fn () => (new FfmpegRenderer)->render([frame()], null, null, new VideoSpec))
            ->toThrow(RuntimeException::class, 'wrapped.video.ffmpeg');
    });
});

describe('refusing bad input before touching ffmpeg', function () {
    it('needs at least one frame', function () {
        expect(fn () => (new FfmpegRenderer)->render([], null, null, new VideoSpec))
            ->toThrow(RuntimeException::class, 'at least one frame');
    });

    it('refuses a soundtrack that does not exist', function () {
        // With no FFmpeg at all, so the check is proven to come first. This
        // is what CI looks like.
        config(['wrapped.video.ffmpeg' => '/definitely/not/ffmpeg']);

        expect(fn () => (new FfmpegRenderer)->render([frame()], null, '/nope/track.mp3', new VideoSpec))
            ->toThrow(RuntimeException::class, 'does not exist');
    });
});

describe('with real ffmpeg', function () {
    it('produces a playable mp4 of the right length, silent', function () {
        $spec = new VideoSpec(width: 108, height: 192, crossfade: 0.2, fps: 10);
        $frames = [frame(1.0), frame(1.5), frame(1.0)];

        $mp4 = withRealFfmpeg()->render($frames, null, null, $spec);

        expect(substr($mp4, 4, 4))->toBe('ftyp')
            ->and(mp4Duration($mp4))->toEqualWithDelta($spec->duration($frames), 0.15);
    });

    it('holds each frame for its own time, not one time for all', function () {
        $spec = new VideoSpec(width: 108, height: 192, crossfade: 0.2, fps: 10);

        $mp4 = withRealFfmpeg()->render([frame(0.5), frame(2.0)], null, null, $spec);

        // 0.5 + 2.0 - 0.2, which no single per-frame time would give.
        expect(mp4Duration($mp4))->toEqualWithDelta(2.3, 0.15);
    });

    it('handles a single frame with no crossfade to make', function () {
        $spec = new VideoSpec(width: 108, height: 192, crossfade: 0.2, fps: 10);

        $mp4 = withRealFfmpeg()->render([frame(1.0)], null, null, $spec);

        expect(mp4Duration($mp4))->toEqualWithDelta(1.0, 0.15);
    });

    it('pins an overlay over the frames', function () {
        $spec = new VideoSpec(width: 108, height: 192, crossfade: 0.2, fps: 10);

        // Rendering with an overlay must succeed and keep the length; whether
        // it stays put is checked on a real render against the seeded site.
        $mp4 = withRealFfmpeg()->render([frame(1.0), frame(1.0)], tinyPng(), null, $spec);

        expect(substr($mp4, 4, 4))->toBe('ftyp')
            ->and(mp4Duration($mp4))->toEqualWithDelta(1.8, 0.15);
    });

    it('lays a soundtrack under the frames and stops when they do', function () {
        $renderer = withRealFfmpeg();
        $spec = new VideoSpec(width: 108, height: 192, crossfade: 0.2, fps: 10);

        // A ten-second tone, longer than the video, so the trim is exercised.
        $tone = sys_get_temp_dir().'/wrapped-tone-'.bin2hex(random_bytes(4)).'.wav';
        exec(sprintf('%s -hide_banner -loglevel error -y -f lavfi -i "sine=frequency=440:duration=10" %s',
            escapeshellarg(config('wrapped.video.ffmpeg') ?: '/opt/homebrew/bin/ffmpeg'), escapeshellarg($tone)));

        if (! is_file($tone)) {
            test()->markTestSkipped('Could not synthesize a test tone.');
        }

        try {
            $mp4 = $renderer->render([frame(1.0), frame(1.0)], null, $tone, $spec);
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

        withRealFfmpeg()->render([frame(0.5)], null, null, new VideoSpec(width: 108, height: 192, fps: 10));

        expect(count(glob(sys_get_temp_dir().'/wrapped-video-*') ?: []))->toBe($before);
    });
});

describe('slicing a sheet', function () {
    it('cuts a grid back into its cells, in reading order, alpha kept', function () {
        $renderer = withRealFfmpeg();

        // A 2x2 sheet of 4x4 cells, each a different solid color, drawn by ffmpeg itself.
        $sheet = sys_get_temp_dir().'/wrapped-sheet-'.bin2hex(random_bytes(4)).'.png';
        exec(sprintf(
            '%s -hide_banner -loglevel error -y -f lavfi -i "color=red:s=4x4" -f lavfi -i "color=green:s=4x4" -f lavfi -i "color=blue:s=4x4" -f lavfi -i "color=black@0.0:s=4x4,format=rgba" -filter_complex "[0][1]hstack[t];[2][3]hstack[b];[t][b]vstack,format=rgba" -frames:v 1 %s',
            escapeshellarg(config('wrapped.video.ffmpeg') ?: '/opt/homebrew/bin/ffmpeg'),
            escapeshellarg($sheet),
        ));

        if (! is_file($sheet)) {
            test()->markTestSkipped('Could not draw a test sheet.');
        }

        try {
            $cells = $renderer->split((string) file_get_contents($sheet), 4, 4, 4, 2);
        } finally {
            @unlink($sheet);
        }

        expect($cells)->toHaveCount(4);

        foreach ($cells as $cell) {
            expect(substr($cell, 1, 3))->toBe('PNG');
        }

        // The fourth cell was transparent black; a PNG with an alpha channel
        // says so in its IHDR color type (6 = RGBA).
        expect(ord($cells[3][25]))->toBe(6);
    });

    it('returns nothing for a count of nothing', function () {
        expect(withRealFfmpeg()->split('not even a png', 4, 4, 0, 2))->toBe([]);
    });
});
