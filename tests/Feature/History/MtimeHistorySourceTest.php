<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\Sources\MtimeHistorySource;
use Carbon\CarbonImmutable;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;

/**
 * Real entry files in a temporary content directory, whose mtimes are then set
 * with touch() — which is the only thing this source reads.
 */
function sites(): void
{
    // Statamic collapses a collection to one site unless multisite is actually on.
    config(['statamic.system.multisite' => true]);

    Site::setSites([
        'default' => ['name' => 'Default', 'url' => '/', 'locale' => 'en_US'],
        'french' => ['name' => 'French', 'url' => '/fr/', 'locale' => 'fr_FR'],
    ]);
}

function entryIn(string $handle, string $id, array $attributes = []): void
{
    if (! Collection::findByHandle($handle)) {
        Collection::make($handle)->dated(false)->sites($attributes['sites'] ?? ['default'])->save();
    }

    Entry::make()
        ->collection($handle)
        ->id($id)
        ->slug($attributes['slug'] ?? $id)
        ->locale($attributes['site'] ?? 'default')
        ->published(true)
        ->data(['title' => $attributes['title'] ?? 'Hello World'])
        ->save();
}

function touchEntry(string $id, string $date): void
{
    $entry = Entry::find($id);

    touch($entry->path(), CarbonImmutable::parse($date)->getTimestamp());

    Stache::clear();
}

function span(int $year): array
{
    return [
        CarbonImmutable::create($year, 1, 1)->startOfDay(),
        CarbonImmutable::create($year, 12, 31)->endOfDay(),
    ];
}

beforeEach(function () {
    $this->content = sys_get_temp_dir().'/wrapped-mtime-'.bin2hex(random_bytes(6));

    mkdir($this->content.'/collections', 0777, true);

    Stache::store('collections')->directory($this->content.'/collections');
    Stache::store('entries')->directory($this->content.'/collections');
    Stache::clear();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->content));
});

it('is the last resort, and says so', function () {
    $source = new MtimeHistorySource;

    expect($source->handle())->toBe('mtime')
        ->and($source->name())->toBe('File modification times')
        ->and($source->confidence())->toBe(Confidence::Low);
});

it('is unavailable when there are no entry files', function () {
    $source = new MtimeHistorySource;

    expect($source->isAvailable())->toBeFalse()
        ->and($source->events(...span(2026)))->toBeEmpty()
        ->and($source->earliestEvent())->toBeNull();
});

it('is available once an entry file exists', function () {
    entryIn('pages', 'page-1');

    expect((new MtimeHistorySource)->isAvailable())->toBeTrue();
});

it('reads a file mtime as an updated event', function () {
    entryIn('pages', 'page-1', ['slug' => 'about', 'title' => 'About Us']);
    touchEntry('page-1', '2026-03-04 09:00:00');

    $event = (new MtimeHistorySource)->events(...span(2026))->sole();

    expect($event->type)->toBe(HistoryEventType::Updated)
        ->and($event->itemType)->toBe('entry')
        ->and($event->itemId)->toBe('page-1')
        ->and($event->site)->toBe('default')
        ->and($event->occurredAt->toDateTimeString())->toBe('2026-03-04 09:00:00')
        ->and($event->meta)->toBe([
            'collection' => 'pages',
            'title' => 'About Us',
            'slug' => 'about',
        ]);
});

it('never claims to know who touched a file', function () {
    entryIn('pages', 'page-1');
    touchEntry('page-1', '2026-03-04 09:00:00');

    expect((new MtimeHistorySource)->events(...span(2026))->sole()->author)->toBeNull();
});

it('never reports a publication, only a write', function () {
    entryIn('pages', 'page-1');
    entryIn('pages', 'page-2');
    touchEntry('page-1', '2026-03-04 09:00:00');
    touchEntry('page-2', '2026-05-05 09:00:00');

    $types = (new MtimeHistorySource)->events(...span(2026))->pluck('type')->unique()->all();

    expect($types)->toBe([HistoryEventType::Updated]);
});

it('gives one event per entry, however many times it was edited', function () {
    entryIn('pages', 'page-1');
    touchEntry('page-1', '2026-03-04 09:00:00');
    touchEntry('page-1', '2026-08-08 09:00:00');

    $events = (new MtimeHistorySource)->events(...span(2026));

    expect($events)->toHaveCount(1)
        ->and($events->sole()->occurredAt->toDateTimeString())->toBe('2026-08-08 09:00:00');
});

it('produces nothing for an entry whose file has gone', function () {
    entryIn('pages', 'page-1');

    unlink(Entry::find('page-1')->path());

    $source = new MtimeHistorySource;

    expect($source->events(...span(2026)))->toBeEmpty()
        ->and($source->earliestEvent())->toBeNull()
        ->and($source->isAvailable())->toBeFalse();
});

it('only returns events inside the window', function () {
    entryIn('pages', 'old');
    entryIn('pages', 'inside');
    entryIn('pages', 'later');
    touchEntry('old', '2025-12-31 23:59:59');
    touchEntry('inside', '2026-07-01 12:00:00');
    touchEntry('later', '2027-01-01 00:00:01');

    expect((new MtimeHistorySource)->events(...span(2026))->pluck('itemId')->all())->toBe(['inside']);
});

it('returns events oldest first', function () {
    entryIn('pages', 'third');
    entryIn('pages', 'first');
    entryIn('pages', 'second');
    touchEntry('third', '2026-09-01 08:00:00');
    touchEntry('first', '2026-01-05 08:00:00');
    touchEntry('second', '2026-04-02 08:00:00');

    expect((new MtimeHistorySource)->events(...span(2026))->pluck('itemId')->all())
        ->toBe(['first', 'second', 'third']);
});

it('filters by site when asked', function () {
    sites();
    entryIn('pages', 'en', ['sites' => ['default', 'french'], 'site' => 'default']);
    entryIn('pages', 'fr', ['sites' => ['default', 'french'], 'site' => 'french']);
    touchEntry('en', '2026-03-04 09:00:00');
    touchEntry('fr', '2026-03-05 09:00:00');

    $source = new MtimeHistorySource;

    expect($source->events(...[...span(2026), 'french'])->pluck('itemId')->all())->toBe(['fr'])
        ->and($source->events(...span(2026))->pluck('itemId')->all())->toBe(['en', 'fr']);
});

it('reports the earliest write', function () {
    entryIn('pages', 'page-1');
    entryIn('pages', 'page-2');
    touchEntry('page-1', '2024-06-10 08:00:00');
    touchEntry('page-2', '2026-01-01 08:00:00');

    expect((new MtimeHistorySource)->earliestEvent()?->toDateTimeString())->toBe('2024-06-10 08:00:00');
});
