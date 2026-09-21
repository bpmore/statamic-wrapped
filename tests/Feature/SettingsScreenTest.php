<?php

use Bpmore\Wrapped\Export\Soundtrack;
use Bpmore\Wrapped\Export\Soundtracks;
use Bpmore\Wrapped\Export\Theme;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Sharing\Share;
use Bpmore\Wrapped\Sharing\ShareLinks;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Contracts\Addons\SettingsRepository;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

/**
 * The settings screen, which exists because of who ends up holding a site.
 *
 * A developer installs this and hands over. The person left with it opens
 * the control panel, wants the cards in the organization's color, and
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

it('shows what was saved when the screen is opened again', function () {
    // Statamic saves an addon's settings under its slug and, on its own,
    // reads them back under its package name. Ours differ, so without the
    // addon's own repository every save looked lost on the next page load.
    savedLook(['background' => '#fef3c7']);

    expect(wrappedAddon()->settings()->raw())->toMatchArray(['background' => '#fef3c7']);

    $this->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper())
        ->get(wrappedAddon()->settingsUrl())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('values.background', '#fef3c7'));
});

it('lets the screen win once it has been saved', function () {
    savedLook(['background' => '#fef3c7']);

    expect(app(Theme::class)->background)->toBe('#fef3c7')
        ->and(app(Theme::class)->isDark())->toBeFalse();
});

it('goes back to the config file for a color that was emptied on the screen', function () {
    // Clearing the field means "not this", and the only "not this" is what
    // the file says. An unsaved field on a saved form is the same case.
    savedLook(['background' => null, 'accent' => '#ffffff']);

    expect(app(Theme::class)->background)->toBe('#123456');
});

it('does not let an untouched field on the screen overrule the config file', function () {
    // Somebody opens the screen, picks an accent, saves. The background on
    // that form still holds the form's own default; reading blended values
    // would quietly repaint a site whose developer chose a color on purpose.
    savedLook(['accent' => '#ffffff']);

    expect(app(Theme::class)->background)->toBe('#123456')
        ->and(app(Theme::class)->accent)->toBe('#ffffff');
});

describe('public links, from the screen', function () {
    it('follows the config file until the screen is saved', function () {
        config(['wrapped.share.enabled' => false]);
        expect(app(ShareLinks::class)->enabled())->toBeFalse();

        config(['wrapped.share.enabled' => true]);
        expect(app(ShareLinks::class)->enabled())->toBeTrue();
    });

    it('lets the screen switch it on', function () {
        config(['wrapped.share.enabled' => false]);
        savedLook(['share_enabled' => true]);

        expect(app(ShareLinks::class)->enabled())->toBeTrue();
    });

    it('lets the screen switch it off, over a config file that says on', function () {
        config(['wrapped.share.enabled' => true]);
        savedLook(['share_enabled' => false]);

        expect(app(ShareLinks::class)->enabled())->toBeFalse();
    });

    it('stops every public link the moment the screen switches it off', function () {
        config(['wrapped.share.enabled' => true]);
        $this->actingAs(User::make()->id('admin')->email('admin@example.com')->makeSuper());
        Snapshot::create([
            'site' => 'default',
            'period' => Period::Year,
            'period_key' => '2026',
            'history_source' => 'logbook',
            'confidence' => Confidence::High,
            'stats' => ['entries_published' => ['count' => 42, 'previous' => null]],
            'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
            'generated_by' => null,
        ]);
        $this->post(cp_route('wrapped.share.store'), ['period' => '2026']);
        $url = Share::sole()->url();

        $this->get($url)->assertOk();

        savedLook(['share_enabled' => false]);

        $this->get($url)->assertNotFound();
    });

    it('draws the toggle starting where the config file is, so saving the look does not switch it off', function () {
        config(['wrapped.share.enabled' => true]);

        $field = wrappedAddon()->settingsBlueprint()->field('share_enabled');

        expect($field->type())->toBe('toggle')
            ->and($field->get('default'))->toBeTrue();
    });
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

describe('your soundtracks, from the settings screen', function () {
    beforeEach(function () {
        $this->root = sys_get_temp_dir().'/wrapped-music-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/assets/music', 0777, true);
        mkdir($this->root.'/containers', 0777, true);

        Stache::store('asset-containers')->directory($this->root.'/containers');
        Stache::clear();

        config(['filesystems.disks.test_assets' => ['driver' => 'local', 'root' => $this->root.'/assets']]);
        config(['wrapped.video.tracks' => []]);
        AssetContainer::make('uploads')->disk('test_assets')->save();

        file_put_contents($this->root.'/assets/music/house-theme_v2.mp3', 'not really audio');
        file_put_contents($this->root.'/assets/music/jingle.m4a', 'not really audio');
        file_put_contents($this->root.'/assets/notes.txt', 'lyrics, maybe');
    });

    afterEach(function () {
        exec('rm -rf '.escapeshellarg($this->root));
    });

    it('offers the site\'s container on the soundtracks field', function () {
        $field = wrappedAddon()->settingsBlueprint()->field('soundtracks');

        expect($field->type())->toBe('assets')
            ->and($field->get('container'))->toBe('uploads')
            ->and($field->get('allow_uploads'))->toBeTrue()
            ->and($field->get('max_files'))->toBeNull();
    });

    it('turns the uploaded files into tracks, named from their filenames, ahead of the bundled ones', function () {
        savedLook(['soundtracks' => ['music/house-theme_v2.mp3', 'music/jingle.m4a']]);

        $all = (new Soundtracks)->all();
        $uploaded = $all->filter(fn (Soundtrack $t) => str_starts_with($t->handle, 'upload-'));

        expect($uploaded)->toHaveCount(2)
            ->and($uploaded->pluck('name')->values()->all())->toBe(['House Theme V2', 'Jingle'])
            ->and($uploaded->first()->bundled)->toBeFalse()
            ->and($uploaded->first()->description)->toBe('Uploaded to this site.')
            ->and($uploaded->first()->mimeType())->toBe('audio/mpeg')
            ->and($all->keys()->first())->toStartWith('upload-')
            ->and($all->last()->bundled)->toBeTrue()
            ->and((new Soundtracks)->default()?->name)->toBe('House Theme V2');
    });

    it('uses a title somebody gave the file', function () {
        AssetContainer::find('uploads')->asset('music/jingle.m4a')->set('title', 'The Podcast Jingle')->save();
        savedLook(['soundtracks' => ['music/jingle.m4a']]);

        expect((new Soundtracks)->all()->first()->name)->toBe('The Podcast Jingle');
    });

    it('passes over a file that is not audio', function () {
        savedLook(['soundtracks' => ['notes.txt', 'music/jingle.m4a']]);

        $uploaded = (new Soundtracks)->all()->filter(fn (Soundtrack $t) => str_starts_with($t->handle, 'upload-'));

        expect($uploaded)->toHaveCount(1)
            ->and($uploaded->first()->name)->toBe('Jingle');
    });

    it('leaves out a file that is gone, and a reference that never was', function () {
        savedLook(['soundtracks' => ['music/jingle.m4a', 'music/missing.mp3', 'uploads::music/house-theme_v2.mp3']]);

        $names = (new Soundtracks)->all()->filter(fn (Soundtrack $t) => str_starts_with($t->handle, 'upload-'))->pluck('name')->values()->all();

        expect($names)->toBe(['Jingle', 'House Theme V2']);
    });

    it('lists config tracks first, then uploads, then the bundled tracks', function () {
        $path = $this->root.'/assets/config-track.mp3';
        file_put_contents($path, 'not really audio');
        config(['wrapped.video.tracks' => ['house' => ['name' => 'From Config', 'path' => $path]]]);
        savedLook(['soundtracks' => ['music/jingle.m4a']]);

        $names = (new Soundtracks)->all()->pluck('name')->values()->all();

        expect(array_slice($names, 0, 2))->toBe(['From Config', 'Jingle'])
            ->and($names[2])->toBe('Soft Landing');
    });

    it('gives the same handle to the same file every time, and a URL-safe one', function () {
        savedLook(['soundtracks' => ['music/jingle.m4a']]);

        $first = (new Soundtracks)->all()->keys()->first();
        $again = (new Soundtracks)->all()->keys()->first();

        expect($first)->toBe($again)->toMatch('/^upload-[a-f0-9]{12}$/');
    });

    it('reads a saved path from the container the form offered, even when another container has the same path', function () {
        // A second container whose handle sorts BEFORE the form's would win a
        // naive "first container with this path" search; a second one that
        // sorts AFTER must not either. So: one of each, both with the path.
        mkdir($this->root.'/aaa/music', 0777, true);
        mkdir($this->root.'/zzz/music', 0777, true);
        config(['filesystems.disks.test_aaa' => ['driver' => 'local', 'root' => $this->root.'/aaa']]);
        config(['filesystems.disks.test_zzz' => ['driver' => 'local', 'root' => $this->root.'/zzz']]);
        AssetContainer::make('aaa')->disk('test_aaa')->save();
        AssetContainer::make('zzz')->disk('test_zzz')->save();
        file_put_contents($this->root.'/aaa/music/jingle.m4a', 'the wrong file, in aaa');
        file_put_contents($this->root.'/zzz/music/jingle.m4a', 'the wrong file, in zzz');
        Stache::clear();

        // The form offers the first container by handle, which is now "aaa";
        // a value saved through it belongs to "aaa".
        expect(wrappedAddon()->settingsBlueprint()->field('soundtracks')->get('container'))->toBe('aaa');
        savedLook(['soundtracks' => ['music/jingle.m4a']]);

        $track = (new Soundtracks)->all()->first();

        expect($track->name)->toBe('Jingle')
            ->and(file_get_contents($track->localPath()))->toBe('the wrong file, in aaa');

        // And a value that names its container is taken at its word.
        savedLook(['soundtracks' => ['zzz::music/jingle.m4a']]);

        expect(file_get_contents((new Soundtracks)->all()->first()->localPath()))->toBe('the wrong file, in zzz');
    });

    it('is streamed to the preview button like any other track', function () {
        savedLook(['soundtracks' => ['music/jingle.m4a']]);
        $handle = (new Soundtracks)->all()->keys()->first();

        $this->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper());

        $this->get(cp_route('wrapped.soundtrack', ['handle' => $handle]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mp4');
    });
});

describe('a site with no asset container', function () {
    beforeEach(function () {
        $this->root = sys_get_temp_dir().'/wrapped-nocontainers-'.bin2hex(random_bytes(6));
        mkdir($this->root, 0777, true);
        Stache::store('asset-containers')->directory($this->root);
        Stache::clear();
    });

    afterEach(function () {
        exec('rm -rf '.escapeshellarg($this->root));
    });

    it('asks for a path or address for the logo instead', function () {
        expect(wrappedAddon()->settingsBlueprint()->field('logo')->type())->toBe('text');
    });

    it('has no soundtracks field, and no Music section left holding nothing', function () {
        $blueprint = wrappedAddon()->settingsBlueprint();

        expect($blueprint->hasField('soundtracks'))->toBeFalse()
            ->and($blueprint->tabs()->first()->sections()->map->display()->all())->not->toContain('Music');
    });
});
