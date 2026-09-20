<?php

use Bpmore\Wrapped\Export\Soundtrack;
use Bpmore\Wrapped\Export\Soundtracks;
use Illuminate\Support\Facades\Log;

function soundtracks(): Soundtracks
{
    // Fresh each time: the registry memoizes, and tests change config.
    return new Soundtracks;
}

/**
 * Temp files made during a test, so afterEach can remove them. A static in a
 * function because a Pest file has no true globals to keep them in.
 *
 * @return list<string>
 */
function trackFiles(?string $add = null, bool $reset = false): array
{
    static $files = [];

    if ($reset) {
        $files = [];
    }

    if ($add !== null) {
        $files[] = $add;
    }

    return $files;
}

function aTrackFile(string $name = 'house-theme.mp3'): string
{
    $path = sys_get_temp_dir().'/wrapped-track-'.bin2hex(random_bytes(4)).'-'.$name;
    file_put_contents($path, 'not really audio');
    trackFiles(add: $path);

    return $path;
}

afterEach(function () {
    foreach (trackFiles() as $file) {
        @unlink($file);
    }

    trackFiles(reset: true);
});

describe('the bundled tracks', function () {
    it('ships every track the manifest lists, and every file the manifest names exists', function () {
        $manifest = require Soundtracks::MANIFEST;
        $tracks = soundtracks()->all();

        expect($tracks)->toHaveCount(count($manifest));

        foreach (array_keys($manifest) as $handle) {
            expect($tracks->get($handle))->toBeInstanceOf(Soundtrack::class)
                ->and($tracks->get($handle)->bundled)->toBeTrue()
                ->and(is_file($tracks->get($handle)->path))->toBeTrue();
        }
    });

    it('lists every audio file in the directory in the manifest, so none ships unlabeled', function () {
        $manifest = require Soundtracks::MANIFEST;
        $files = collect(glob(Soundtracks::BUNDLED_DIRECTORY.'/*.{m4a,mp3,ogg,opus,wav,flac,aac}', GLOB_BRACE) ?: [])
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->sort()
            ->values();

        expect($files->all())->toBe(collect(array_keys($manifest))->sort()->values()->all());
    });

    it('keeps suno\'s metadata in every bundled file', function () {
        // The terms require the embedded metadata to stay. A file that lost it
        // was converted somewhere it should not have been.
        foreach (soundtracks()->all() as $track) {
            $header = (string) file_get_contents($track->path, false, null, 0, 65536);

            // str_contains rather than toContain(): the header is binary and
            // the expectation's string handling does not cope with that.
            expect(str_contains($header, 'made with suno'))->toBeTrue("{$track->handle} has lost its Suno metadata");
        }
    });

    it('can be switched off', function () {
        config(['wrapped.video.bundled' => false]);

        expect(soundtracks()->all())->toBeEmpty()
            ->and(soundtracks()->isEmpty())->toBeTrue();
    });

    it('states a real mime type for an m4a, which the browser would otherwise have to guess', function () {
        expect(soundtracks()->find('open-road')?->mimeType())->toBe('audio/mp4');
    });
});

describe('site-supplied tracks', function () {
    it('are offered alongside the bundled ones, and first', function () {
        config(['wrapped.video.tracks' => [
            'house-theme' => ['name' => 'House Theme', 'description' => 'From the podcast.', 'path' => aTrackFile()],
        ]]);

        $all = soundtracks()->all();

        expect($all->keys()->first())->toBe('house-theme')
            ->and($all->get('house-theme')->bundled)->toBeFalse()
            ->and($all->get('house-theme')->description)->toBe('From the podcast.')
            ->and($all->get('house-theme')->mimeType())->toBe('audio/mpeg')
            ->and($all->count())->toBeGreaterThan(1);
    });

    it('become the default when present', function () {
        config(['wrapped.video.tracks' => [
            'house-theme' => ['name' => 'House Theme', 'path' => aTrackFile()],
        ]]);

        expect(soundtracks()->default()?->handle)->toBe('house-theme');
    });

    it('fall back to a name from the handle', function () {
        config(['wrapped.video.tracks' => [
            'late-night-radio' => ['path' => aTrackFile()],
        ]]);

        expect(soundtracks()->find('late-night-radio')?->name)->toBe('Late Night Radio');
    });

    it('are left out, and logged, when the file is missing', function () {
        Log::spy();

        config(['wrapped.video.tracks' => [
            'gone' => ['name' => 'Gone', 'path' => '/nowhere/gone.mp3'],
        ]]);

        expect(soundtracks()->find('gone'))->toBeNull();

        Log::shouldHaveReceived('warning')->once();
    });

    it('ignore entries that are not shaped like a track', function () {
        config(['wrapped.video.tracks' => [
            'a string' => 'not an array',
            42 => ['name' => 'Numeric key', 'path' => aTrackFile()],
        ]]);

        expect(soundtracks()->all()->filter(fn (Soundtrack $t) => ! $t->bundled))->toBeEmpty();
    });
});

it('defaults to the first bundled track when the site has none', function () {
    $first = array_key_first(require Soundtracks::MANIFEST);

    expect(soundtracks()->default()?->handle)->toBe($first);
});

it('finds nothing for a handle it does not have', function () {
    expect(soundtracks()->find('nope'))->toBeNull();
});

it('serializes for the picker without the file path', function () {
    $array = soundtracks()->find('open-road')?->toArray();

    expect($array)->toHaveKeys(['handle', 'name', 'description', 'bundled'])
        ->and($array)->not->toHaveKey('path');
});
