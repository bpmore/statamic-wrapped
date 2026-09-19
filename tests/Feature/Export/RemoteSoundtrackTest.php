<?php

use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\Export\Soundtrack;
use Bpmore\Wrapped\Export\Soundtracks;
use Bpmore\Wrapped\Export\VideoRenderer;
use Bpmore\Wrapped\Export\WrappedVideo;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Bpmore\Wrapped\Tests\Fixtures\FakeVideoRenderer;
use Bpmore\Wrapped\Tests\Fixtures\NotLocalAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Statamic\Contracts\Addons\SettingsRepository;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

/*
 * An uploaded soundtrack in a container that is not on this server's disk:
 * S3 in life, a stand-in adapter here. FFmpeg needs a file, so the track
 * becomes one for the length of a render and no longer.
 */

const REMOTE_BYTES = 'not really audio, but bytes all the same';

function remoteUploaded(): Soundtrack
{
    $settings = Addon::get('bpmore/statamic-wrapped')->settings();
    $settings->set('soundtracks', ['music/jingle.m4a']);
    app(SettingsRepository::class)->save($settings);

    return (new Soundtracks)->all()->first(fn (Soundtrack $t) => str_starts_with($t->handle, 'upload-'))
        ?? throw new RuntimeException('The upload did not become a track.');
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/wrapped-remote-'.bin2hex(random_bytes(6));
    mkdir($this->root.'/files/music', 0777, true);
    mkdir($this->root.'/containers', 0777, true);

    Stache::store('asset-containers')->directory($this->root.'/containers');
    Stache::clear();

    // A disk whose adapter is not local, whatever the bytes underneath.
    Storage::extend('notlocal', function ($app, array $config) {
        $adapter = new NotLocalAdapter($config['root']);

        return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
    });
    config(['filesystems.disks.remote_assets' => ['driver' => 'notlocal', 'root' => $this->root.'/files']]);
    config(['wrapped.video.tracks' => [], 'wrapped.video.bundled' => false]);

    AssetContainer::make('remote')->disk('remote_assets')->save();
    file_put_contents($this->root.'/files/music/jingle.m4a', REMOTE_BYTES);

    app()->instance(ImageRenderer::class, new FakeImageRenderer);
    app()->instance(VideoRenderer::class, $this->videoRenderer = new FakeVideoRenderer);

    $this->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper());
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->root));
    foreach (glob(sys_get_temp_dir().'/wrapped-track-upload-*') ?: [] as $stray) {
        @unlink($stray);
    }
});

it('is offered as a track without a file of its own', function () {
    $track = remoteUploaded();

    expect($track->isLocal())->toBeFalse()
        ->and($track->path)->toBeNull()
        ->and($track->name)->toBe('Jingle')
        ->and($track->extension())->toBe('m4a')
        ->and($track->mimeType())->toBe('audio/mp4');
});

it('becomes a file when asked, the same file each time, and stops being one when released', function () {
    $track = remoteUploaded();

    $copy = $track->localPath();

    expect($copy)->toEndWith('.m4a')
        ->and(is_file($copy))->toBeTrue()
        ->and(file_get_contents($copy))->toBe(REMOTE_BYTES)
        ->and($track->localPath())->toBe($copy);

    $track->release();

    expect(is_file($copy))->toBeFalse();

    // Twice is fine.
    $track->release();
});

it('is a file for exactly the length of a render', function () {
    $track = remoteUploaded();

    $snapshot = Snapshot::create([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => ['entries_published' => ['count' => 42, 'previous' => null]],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
    ]);

    app(WrappedVideo::class)->render($snapshot, ['entries_published'], $track->handle);

    expect($this->videoRenderer->audio)->toEndWith('.m4a')
        ->and($this->videoRenderer->audioExisted)->toBeTrue()
        ->and(is_file($this->videoRenderer->audio))->toBeFalse();
});

it('is cleaned up even when the render fails', function () {
    $track = remoteUploaded();
    app()->instance(VideoRenderer::class, $this->videoRenderer = new FakeVideoRenderer(fails: 'FFmpeg fell over'));

    $snapshot = Snapshot::create([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => ['entries_published' => ['count' => 42, 'previous' => null]],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
    ]);

    expect(fn () => app(WrappedVideo::class)->render($snapshot, ['entries_published'], $track->handle))
        ->toThrow(RuntimeException::class);

    expect(glob(sys_get_temp_dir().'/wrapped-track-upload-*'))->toBe([]);
});

it('streams to the preview button straight from its container', function () {
    $track = remoteUploaded();

    $response = $this->get(cp_route('wrapped.soundtrack', ['handle' => $track->handle]));

    $response->assertOk()->assertHeader('Content-Type', 'audio/mp4');

    expect($response->streamedContent())->toBe(REMOTE_BYTES);
});

it('is left out, and logged, when the file is gone from its container', function () {
    unlink($this->root.'/files/music/jingle.m4a');
    Stache::clear();

    $settings = Addon::get('bpmore/statamic-wrapped')->settings();
    $settings->set('soundtracks', ['music/jingle.m4a']);
    app(SettingsRepository::class)->save($settings);

    expect((new Soundtracks)->all())->toBeEmpty();
});
