<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Cards\AssetsUploadedCard;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Stache;

function uploadFile(string $path, int $bytes): void
{
    file_put_contents(test()->assets.'/'.$path, str_repeat('x', $bytes));
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/wrapped-assets-'.bin2hex(random_bytes(6));
    $this->assets = $this->root.'/assets';

    mkdir($this->assets, 0777, true);
    mkdir($this->root.'/containers', 0777, true);

    Stache::store('asset-containers')->directory($this->root.'/containers');
    Stache::clear();

    config(['filesystems.disks.test_assets' => [
        'driver' => 'local',
        'root' => $this->assets,
    ]]);

    AssetContainer::make('uploads')->disk('test_assets')->save();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->root));
});

it('only appears on a site with a real audit trail', function () {
    expect((new AssetsUploadedCard)->requires())->toBe(Confidence::High)
        ->and((new AssetsUploadedCard)->handle())->toBe('assets_uploaded');
});

it('counts the uploads and adds up what they weigh', function () {
    uploadFile('one.txt', 100);
    uploadFile('two.txt', 250);

    $card = (new AssetsUploadedCard)->compute(cardContext([
        wasCreated('uploads::one.txt', '2026-02-01 09:00:00', itemType: 'asset'),
        wasCreated('uploads::two.txt', '2026-06-01 09:00:00', itemType: 'asset'),
    ]));

    expect($card)->toBe(['count' => 2, 'bytes' => 350, 'weighed' => 2]);
});

it('counts a file uploaded twice only once', function () {
    uploadFile('one.txt', 100);

    $card = (new AssetsUploadedCard)->compute(cardContext([
        wasCreated('uploads::one.txt', '2026-02-01 09:00:00', itemType: 'asset'),
        wasCreated('uploads::one.txt', '2026-06-01 09:00:00', itemType: 'asset'),
    ]));

    expect($card)->toBe(['count' => 1, 'bytes' => 100, 'weighed' => 1]);
});

it('still counts an upload that has since been deleted, but cannot weigh it', function () {
    uploadFile('one.txt', 100);

    $card = (new AssetsUploadedCard)->compute(cardContext([
        wasCreated('uploads::one.txt', '2026-02-01 09:00:00', itemType: 'asset'),
        wasCreated('uploads::gone.txt', '2026-06-01 09:00:00', itemType: 'asset'),
    ]));

    expect($card)->toBe(['count' => 2, 'bytes' => 100, 'weighed' => 1]);
});

it('survives an upload into a container that no longer exists', function () {
    $card = (new AssetsUploadedCard)->compute(cardContext([
        wasCreated('deleted-container::one.txt', '2026-02-01 09:00:00', itemType: 'asset'),
    ]));

    expect($card)->toBe(['count' => 1, 'bytes' => 0, 'weighed' => 0]);
});

it('ignores events that are not uploads', function () {
    uploadFile('one.txt', 100);

    $card = (new AssetsUploadedCard)->compute(cardContext([
        wasCreated('uploads::one.txt', '2026-02-01 09:00:00', itemType: 'asset'),
        wasUpdated('uploads::two.txt', '2026-03-01 09:00:00', itemType: 'asset'),
        wasCreated('entry-1', '2026-04-01 09:00:00'),
    ]));

    expect($card['count'])->toBe(1);
});

it('shows no card when nothing was uploaded', function () {
    expect((new AssetsUploadedCard)->compute(cardContext([])))->toBeNull();
});
