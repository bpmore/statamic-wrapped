<?php

use Bpmore\Wrapped\Export\Theme;
use Statamic\Contracts\Addons\SettingsRepository;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Stache;

/**
 * The settings screen, which exists because of who ends up holding a site.
 *
 * A developer installs this and hands over. The person left with it opens
 * the control panel, wants the cards in the organisation's colour, and
 * cannot edit config/wrapped.php. Everything here is about that working.
 */
const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function wrappedAddon(): Statamic\Addons\Addon
{
    return Addon::get('bpmore/statamic-wrapped');
}

/**
 * Save exactly these values as if someone had pressed Save on the screen.
 *
 * @param  array<string, mixed>  $values
 */
function savedLook(array $values): void
{
    $settings = wrappedAddon()->settings();

    foreach ($values as $key => $value) {
        $settings->set($key, $value);
    }

    app(SettingsRepository::class)->save($settings);

    // Resolved fresh, because the real request that reads this is a
    // different one from the request that saved it.
    app()->forgetInstance(Theme::class);
}

beforeEach(function () {
    config(['wrapped.theme' => ['background' => '#123456', 'accent' => null, 'logo' => null]]);
    config(['statamic.cp.custom_logo_url' => null]);
    app()->forgetInstance(Theme::class);
});

it('offers a settings screen at all', function () {
    expect(wrappedAddon()->hasSettingsBlueprint())->toBeTrue()
        ->and(wrappedAddon()->settingsUrl())->toContain('addons');
});

it('asks in plain words, with no handles or file paths', function () {
    $text = collect(wrappedAddon()->settingsBlueprint()->fields()->all())
        ->flatMap(fn ($field) => [$field->display(), $field->instructions()])
        ->filter()
        ->implode(' ');

    expect($text)->toContain('Background')->toContain('Logo');

    foreach (['config/', 'blueprint', 'handle', '.env', 'yaml', 'WRAPPED_'] as $jargon) {
        expect(str_contains(strtolower($text), strtolower($jargon)))
            ->toBeFalse("the settings screen must not say '{$jargon}' to somebody who was handed a site");
    }
});

it('uses the config file until somebody saves the screen', function () {
    expect(app(Theme::class)->background)->toBe('#123456');
});

it('lets the screen win once it has been saved', function () {
    savedLook(['background' => '#fef3c7']);

    expect(app(Theme::class)->background)->toBe('#fef3c7')
        ->and(app(Theme::class)->isDark())->toBeFalse();
});

it('goes back to the config file for a colour that was emptied on the screen', function () {
    // Clearing the field means "not this", and the only "not this" is what
    // the file says. An unsaved field on a saved form is the same case.
    savedLook(['background' => null, 'accent' => '#ffffff']);

    expect(app(Theme::class)->background)->toBe('#123456');
});

it('does not let an untouched field on the screen overrule the config file', function () {
    // Somebody opens the screen, picks an accent, saves. The background on
    // that form still holds the form's own default; reading blended values
    // would quietly repaint a site whose developer chose a colour on purpose.
    savedLook(['accent' => '#ffffff']);

    expect(app(Theme::class)->background)->toBe('#123456')
        ->and(app(Theme::class)->accent)->toBe('#ffffff');
});

describe('the logo, from an asset container', function () {
    beforeEach(function () {
        $this->root = sys_get_temp_dir().'/wrapped-settings-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/assets', 0777, true);
        mkdir($this->root.'/containers', 0777, true);

        Stache::store('asset-containers')->directory($this->root.'/containers');
        Stache::clear();

        config(['filesystems.disks.test_assets' => ['driver' => 'local', 'root' => $this->root.'/assets']]);
        AssetContainer::make('uploads')->disk('test_assets')->save();

        file_put_contents($this->root.'/assets/badge.png', base64_decode(PNG));
    });

    afterEach(function () {
        exec('rm -rf '.escapeshellarg($this->root));
    });

    it('offers the site\'s container on the logo field', function () {
        $field = wrappedAddon()->settingsBlueprint()->field('logo');

        expect($field->type())->toBe('assets')
            ->and($field->get('container'))->toBe('uploads')
            ->and($field->get('max_files'))->toBe(1);
    });

    it('reads the chosen asset into the theme', function () {
        savedLook(['logo' => 'badge.png']);

        expect(app(Theme::class)->logo)->toStartWith('data:image/png;base64,');
    });

    it('reads a container-qualified reference too', function () {
        savedLook(['logo' => 'uploads::badge.png']);

        expect(app(Theme::class)->logo)->toStartWith('data:image/png;base64,');
    });

    it('has no logo for an asset that is gone', function () {
        savedLook(['logo' => 'missing.png']);

        expect(app(Theme::class)->logo)->toBeNull();
    });
});

it('asks for a path or address instead on a site with no asset container', function () {
    $root = sys_get_temp_dir().'/wrapped-nocontainers-'.bin2hex(random_bytes(6));
    mkdir($root, 0777, true);
    Stache::store('asset-containers')->directory($root);
    Stache::clear();

    try {
        $field = wrappedAddon()->settingsBlueprint()->field('logo');

        expect($field->type())->toBe('text');
    } finally {
        exec('rm -rf '.escapeshellarg($root));
    }
});
