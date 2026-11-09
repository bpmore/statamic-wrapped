<?php

use Bpmore\Wrapped\Export\CardImages;
use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\Export\Theme;
use Bpmore\Wrapped\Export\VideoFrames;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Stache;

function themed(array $config): Theme
{
    config(['wrapped.theme' => $config + ['background' => null, 'accent' => null, 'logo' => null]]);
    config(['statamic.cp.custom_logo_url' => null]);

    return Theme::fromConfig();
}

it('is the dark look by default', function () {
    $theme = themed([]);

    expect($theme->background)->toBe('#16161d')
        ->and($theme->text)->toBe('#f7f7f8')
        ->and($theme->isDark())->toBeTrue()
        ->and($theme->accent)->toBeNull()
        ->and($theme->logo)->toBeNull();
});

it('puts dark text on a light background and light text on a dark one', function () {
    expect(themed(['background' => '#ffffff'])->text)->toBe('#16161d')
        ->and(themed(['background' => '#fef3c7'])->text)->toBe('#16161d')
        ->and(themed(['background' => '#1e3a5f'])->text)->toBe('#f7f7f8')
        ->and(themed(['background' => '#000000'])->text)->toBe('#f7f7f8');
});

it('always clears AA contrast for text, whatever background a site picks', function (string $background) {
    $theme = themed(['background' => $background]);

    expect(Theme::contrast($theme->text, $theme->background))->toBeGreaterThanOrEqual(4.5);
})->with(['#ffffff', '#000000', '#808080', '#e11d48', '#0ea5e9', '#84cc16', '#7c3aed', '#fbbf24', '#6b7280', '#f3f4f6']);

it('keeps the muted label color readable too', function (string $background) {
    $theme = themed(['background' => $background]);

    expect(Theme::contrast($theme->muted, $theme->background))->toBeGreaterThanOrEqual(4.5)
        // And it really is quieter than the text, not just the text again.
        ->and($theme->muted)->not->toBe($theme->text);
})->with(['#16161d', '#ffffff', '#1e3a5f', '#fef3c7']);

it('measures the shipped gray at what was checked by hand', function () {
    expect(round(Theme::contrast('#8b8b96', '#16161d'), 2))->toBe(5.34);
});

it('accepts short hex and normalizes it', function () {
    expect(themed(['background' => '#FFF'])->background)->toBe('#ffffff')
        ->and(themed(['background' => ' #1e3a5f '])->background)->toBe('#1e3a5f');
});

it('falls back to the default for anything that is not a color', function (mixed $bad) {
    expect(themed(['background' => $bad])->background)->toBe('#16161d');
})->with(['red', '#12345', 'url(x)', '#16161d; color: red', 42, '']);

it('uses an accent that can be read', function () {
    expect(themed(['background' => '#16161d', 'accent' => '#fbbf24'])->accent)->toBe('#fbbf24');
});

it('drops an accent that cannot be read, and says so', function () {
    Log::spy();

    // Dark blue on near-black: about 1.5:1.
    expect(themed(['background' => '#16161d', 'accent' => '#1e3a5f'])->accent)->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});

/** A one-pixel PNG under the site's public directory; the caller unlinks it. */
function publicLogo(string $name = 'logo'): string
{
    $path = public_path('wrapped-test-'.$name.'-'.bin2hex(random_bytes(4)).'.png');
    file_put_contents($path, onePixelPng());

    return $path;
}

function onePixelPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
}

/**
 * The logo is shown on the public story page and can be set from the
 * settings screen, so where it may be read from is a security boundary,
 * not a convenience: a file under public/, or an asset, and nothing else.
 */
describe('the logo', function () {
    beforeEach(fn () => Cache::flush());

    it('becomes a data uri from a file under public/, so a render with no network can show it', function () {
        $path = publicLogo();

        try {
            expect(themed(['logo' => $path])->logo)->toStartWith('data:image/png;base64,');
        } finally {
            @unlink($path);
        }
    });

    it('maps its URL on this site, and its path from the site root, to the file under public/', function () {
        $path = publicLogo();
        $name = basename($path);

        try {
            expect(themed(['logo' => '/'.$name])->logo)->toStartWith('data:image/png;base64,')
                ->and(themed(['logo' => '/'.$name.'?v=3'])->logo)->toStartWith('data:image/png;base64,')
                ->and(themed(['logo' => url('/'.$name)])->logo)->toStartWith('data:image/png;base64,');
        } finally {
            @unlink($path);
        }
    });

    it('falls back to the control panel logo', function () {
        $path = publicLogo('cp');

        try {
            config(['wrapped.theme' => ['background' => null, 'accent' => null, 'logo' => null]]);
            config(['statamic.cp.custom_logo_url' => '/'.basename($path)]);

            expect(Theme::fromConfig()->logo)->toStartWith('data:image/png;base64,');
        } finally {
            @unlink($path);
        }
    });

    it('reads nothing outside public/, however the path gets there', function () {
        // A readable image that is simply elsewhere on disk.
        $outside = sys_get_temp_dir().'/wrapped-logo-'.bin2hex(random_bytes(4)).'.png';
        file_put_contents($outside, onePixelPng());

        // A symlink under public/ pointing out of it.
        $link = public_path('wrapped-test-link-'.bin2hex(random_bytes(4)).'.png');
        symlink($outside, $link);

        try {
            expect(is_file($outside))->toBeTrue()
                ->and(themed(['logo' => $outside])->logo)->toBeNull()
                ->and(themed(['logo' => $link])->logo)->toBeNull()
                ->and(themed(['logo' => '/'.basename($link)])->logo)->toBeNull()
                ->and(themed(['logo' => '/../'.basename($outside)])->logo)->toBeNull()
                ->and(themed(['logo' => '/etc/hosts'])->logo)->toBeNull()
                ->and(themed(['logo' => public_path('../composer.json')])->logo)->toBeNull();
        } finally {
            @unlink($link);
            @unlink($outside);
        }
    });

    it('fetches nothing from another host', function () {
        expect(themed(['logo' => 'https://example.com/logo.png'])->logo)->toBeNull()
            ->and(themed(['logo' => 'http://127.0.0.1:9/logo.png'])->logo)->toBeNull()
            ->and(themed(['logo' => 'file://'.public_path('anything.png')])->logo)->toBeNull();
    });

    it('is left out when the file is not there', function () {
        expect(themed(['logo' => '/nowhere/logo.png'])->logo)->toBeNull();
    });

    it('is left out when the file is not an image', function () {
        $path = public_path('wrapped-test-notlogo-'.bin2hex(random_bytes(4)).'.txt');
        file_put_contents($path, 'hello');

        try {
            expect(themed(['logo' => $path])->logo)->toBeNull();
        } finally {
            @unlink($path);
        }
    });

    it('is read once per version of the file, not once per page view', function () {
        $path = publicLogo();
        $first = themed(['logo' => $path])->logo;

        try {
            // Same mtime, different bytes: the cached answer stands.
            $mtime = filemtime($path);
            file_put_contents($path, "\x89PNG\r\n\x1a\nchanged");
            touch($path, $mtime);
            clearstatcache();

            expect(themed(['logo' => $path])->logo)->toBe($first);

            // A newer file is a new version and is read again.
            touch($path, $mtime + 60);
            clearstatcache();

            expect(themed(['logo' => $path])->logo)->not->toBe($first)
                ->and(themed(['logo' => $path])->logo)->toContain(base64_encode("\x89PNG\r\n\x1a\nchanged"));
        } finally {
            @unlink($path);
        }
    });

    describe('from an asset', function () {
        beforeEach(function () {
            $this->root = sys_get_temp_dir().'/wrapped-theme-assets-'.bin2hex(random_bytes(4));
            mkdir($this->root.'/offered', 0777, true);
            mkdir($this->root.'/other', 0777, true);
            config(['filesystems.disks.test_offered' => ['driver' => 'local', 'root' => $this->root.'/offered']]);
            config(['filesystems.disks.test_other' => ['driver' => 'local', 'root' => $this->root.'/other']]);
            // "a" sorts first, so it is the container the settings form offers.
            AssetContainer::make('a_offered')->disk('test_offered')->save();
            AssetContainer::make('b_other')->disk('test_other')->save();
            file_put_contents($this->root.'/offered/badge.png', onePixelPng());
            file_put_contents($this->root.'/other/badge.png', onePixelPng());
            Stache::clear();
        });

        afterEach(function () {
            // The containers are written under tests/__fixtures__; take them
            // back out so a run leaves the tree as it found it.
            AssetContainer::find('a_offered')?->delete();
            AssetContainer::find('b_other')?->delete();
            exec('rm -rf '.escapeshellarg($this->root));
        });

        it('reads an asset from the container the settings screen offers', function () {
            $asset = AssetContainer::find('a_offered')->asset('badge.png');

            expect(Theme::fromValues(['logo' => $asset])->logo)->toStartWith('data:image/png;base64,');
        });

        it('ignores an asset from any other container', function () {
            $asset = AssetContainer::find('b_other')->asset('badge.png');

            expect(Theme::fromValues(['logo' => $asset])->logo)->toBeNull();
        });
    });
});

describe('reaching the output', function () {
    beforeEach(function () {
        app()->instance(ImageRenderer::class, $this->renderer = new FakeImageRenderer);
        app()->forgetInstance(Theme::class);
    });

    it('colors the card image', function () {
        config(['wrapped.theme.background' => '#fef3c7']);
        app()->instance(Theme::class, Theme::fromConfig());

        app(CardImages::class)->card(exportable(), 'entries_published');

        expect($this->renderer->html)->toContain('background: #fef3c7')
            ->and($this->renderer->html)->toContain('color: #16161d')
            ->and($this->renderer->html)->not->toContain('#f7f7f8');
    });

    it('colors the video frames, and pins a matching footer', function () {
        config(['wrapped.theme.background' => '#1e3a5f']);
        app()->instance(Theme::class, Theme::fromConfig());

        $frames = app(VideoFrames::class);
        $frames->card(aSnapshot(), ['handle' => 'x', 'heading' => 'H', 'body' => 'B']);

        expect($this->renderer->html)->toContain('background: #1e3a5f')
            ->and($this->renderer->html)->toContain('color: #f7f7f8');

        $frames->footer(aSnapshot());

        expect($this->renderer->html)->toContain('background: transparent')
            ->and($this->renderer->html)->toContain('color: #f7f7f8');
    });

    it('puts the logo on the title frames and nowhere else', function () {
        $path = publicLogo('frames');
        config(['wrapped.theme.logo' => $path]);
        app()->instance(Theme::class, Theme::fromConfig());

        try {
            $frames = app(VideoFrames::class);

            $frames->intro(aSnapshot());
            expect($this->renderer->html)->toContain('data:image/png;base64,');

            $frames->outro(aSnapshot());
            expect($this->renderer->html)->toContain('data:image/png;base64,');

            $frames->card(aSnapshot(), ['handle' => 'x', 'heading' => 'H', 'body' => 'B']);
            expect($this->renderer->html)->not->toContain('data:image/png');
        } finally {
            @unlink($path);
        }
    });
});
