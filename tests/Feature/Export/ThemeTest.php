<?php

use Bpmore\Wrapped\Export\CardImages;
use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\Export\Theme;
use Bpmore\Wrapped\Export\VideoFrames;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Illuminate\Support\Facades\Log;

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

it('keeps the muted label colour readable too', function (string $background) {
    $theme = themed(['background' => $background]);

    expect(Theme::contrast($theme->muted, $theme->background))->toBeGreaterThanOrEqual(4.5)
        // And it really is quieter than the text, not just the text again.
        ->and($theme->muted)->not->toBe($theme->text);
})->with(['#16161d', '#ffffff', '#1e3a5f', '#fef3c7']);

it('measures the shipped grey at what was checked by hand', function () {
    expect(round(Theme::contrast('#8b8b96', '#16161d'), 2))->toBe(5.34);
});

it('accepts short hex and normalises it', function () {
    expect(themed(['background' => '#FFF'])->background)->toBe('#ffffff')
        ->and(themed(['background' => ' #1e3a5f '])->background)->toBe('#1e3a5f');
});

it('falls back to the default for anything that is not a colour', function (mixed $bad) {
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

describe('the logo', function () {
    it('becomes a data uri from a file on disk, so a render with no network can show it', function () {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $path = sys_get_temp_dir().'/wrapped-logo-'.bin2hex(random_bytes(4)).'.png';
        file_put_contents($path, $png);

        try {
            expect(themed(['logo' => $path])->logo)->toStartWith('data:image/png;base64,');
        } finally {
            @unlink($path);
        }
    });

    it('falls back to the control panel logo', function () {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $path = sys_get_temp_dir().'/wrapped-cplogo-'.bin2hex(random_bytes(4)).'.png';
        file_put_contents($path, $png);

        try {
            config(['wrapped.theme' => ['background' => null, 'accent' => null, 'logo' => null]]);
            config(['statamic.cp.custom_logo_url' => $path]);

            expect(Theme::fromConfig()->logo)->toStartWith('data:image/png;base64,');
        } finally {
            @unlink($path);
        }
    });

    it('is left out when the file is not there', function () {
        expect(themed(['logo' => '/nowhere/logo.png'])->logo)->toBeNull();
    });

    it('is left out when the file is not an image', function () {
        $path = sys_get_temp_dir().'/wrapped-notlogo-'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($path, 'hello');

        try {
            expect(themed(['logo' => $path])->logo)->toBeNull();
        } finally {
            @unlink($path);
        }
    });
});

describe('reaching the output', function () {
    beforeEach(function () {
        app()->instance(ImageRenderer::class, $this->renderer = new FakeImageRenderer);
        app()->forgetInstance(Theme::class);
    });

    it('colours the card image', function () {
        config(['wrapped.theme.background' => '#fef3c7']);
        app()->instance(Theme::class, Theme::fromConfig());

        app(CardImages::class)->card(exportable(), 'entries_published');

        expect($this->renderer->html)->toContain('background: #fef3c7')
            ->and($this->renderer->html)->toContain('color: #16161d')
            ->and($this->renderer->html)->not->toContain('#f7f7f8');
    });

    it('colours the video frames, and pins a matching footer', function () {
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
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $path = sys_get_temp_dir().'/wrapped-logo-'.bin2hex(random_bytes(4)).'.png';
        file_put_contents($path, $png);
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
