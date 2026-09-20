<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\Sources\EntryDataHistorySource;
use Carbon\CarbonImmutable;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;

/**
 * Real entries in a real (temporary) content directory, so these tests exercise
 * Statamic's own date, status and localization behavior rather than a stand-in.
 */
function useSites(): void
{
    // Statamic collapses a collection to one site unless multisite is actually on.
    config(['statamic.system.multisite' => true]);

    Site::setSites([
        'default' => ['name' => 'Default', 'url' => '/', 'locale' => 'en_US'],
        'french' => ['name' => 'French', 'url' => '/fr/', 'locale' => 'fr_FR'],
    ]);
}

function makeCollection(string $handle, bool $dated = true, array $sites = ['default']): void
{
    Collection::make($handle)->dated($dated)->sites($sites)->save();
}

/**
 * @param  array<string, mixed>  $data
 */
function makeEntry(string $id, array $data = [], array $attributes = []): void
{
    $entry = Entry::make()
        ->collection($attributes['collection'] ?? 'blog')
        ->id($id)
        ->slug($attributes['slug'] ?? $id)
        ->locale($attributes['site'] ?? 'default')
        ->published($attributes['published'] ?? true)
        ->data(array_merge(['title' => 'Hello World'], $data));

    if (array_key_exists('date', $attributes)) {
        $entry->date($attributes['date']);
    }

    $entry->save();
}

function moment(string $date): int
{
    return CarbonImmutable::parse($date)->getTimestamp();
}

function year(int $year): array
{
    return [
        CarbonImmutable::create($year, 1, 1)->startOfDay(),
        CarbonImmutable::create($year, 12, 31)->endOfDay(),
    ];
}

beforeEach(function () {
    $this->content = sys_get_temp_dir().'/wrapped-content-'.bin2hex(random_bytes(6));

    mkdir($this->content.'/collections', 0777, true);

    Stache::store('collections')->directory($this->content.'/collections');
    Stache::store('entries')->directory($this->content.'/collections');
    Stache::clear();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->content));
});

it('describes itself as partial: timestamps we believe, no edit trail', function () {
    $source = new EntryDataHistorySource;

    expect($source->handle())->toBe('entry_data')
        ->and($source->name())->toBe('Entry data')
        ->and($source->confidence())->toBe(Confidence::Partial);
});

it('is unavailable when there are no entries', function () {
    $source = new EntryDataHistorySource;

    expect($source->isAvailable())->toBeFalse()
        ->and($source->events(...year(2026)))->toBeEmpty()
        ->and($source->earliestEvent())->toBeNull();
});

it('is unavailable when entries carry no dates at all', function () {
    makeCollection('pages', dated: false);
    makeEntry('page-1', attributes: ['collection' => 'pages']);

    expect((new EntryDataHistorySource)->isAvailable())->toBeFalse();
});

it('is available once one entry yields something', function () {
    makeCollection('blog');
    makeEntry('entry-1', attributes: ['date' => '2026-03-04']);

    expect((new EntryDataHistorySource)->isAvailable())->toBeTrue();
});

it('reads a published date as a published event', function () {
    makeCollection('blog');
    makeEntry('entry-1', ['author' => 'user-2'], ['date' => '2026-03-04', 'slug' => 'hello']);

    $event = (new EntryDataHistorySource)->events(...year(2026))->sole();

    expect($event->type)->toBe(HistoryEventType::Published)
        ->and($event->itemType)->toBe('entry')
        ->and($event->itemId)->toBe('entry-1')
        ->and($event->author)->toBe('user-2')
        ->and($event->site)->toBe('default')
        ->and($event->occurredAt->toDateString())->toBe('2026-03-04')
        ->and($event->meta)->toBe([
            'collection' => 'blog',
            'title' => 'Hello World',
            'slug' => 'hello',
            'status' => 'published',
        ]);
});

it('reads updated_at as an updated event, with updated_by as the author', function () {
    makeCollection('pages', dated: false);
    makeEntry('page-1', [
        'updated_at' => moment('2026-06-02 14:30:00'),
        'updated_by' => 'user-1',
    ], ['collection' => 'pages']);

    $event = (new EntryDataHistorySource)->events(...year(2026))->sole();

    expect($event->type)->toBe(HistoryEventType::Updated)
        ->and($event->author)->toBe('user-1')
        ->and($event->occurredAt->toDateTimeString())->toBe('2026-06-02 14:30:00');
});

it('yields both events for an entry that was published and later edited', function () {
    makeCollection('blog');
    makeEntry('entry-1', ['updated_at' => moment('2026-06-02 14:30:00')], ['date' => '2026-03-04']);

    $events = (new EntryDataHistorySource)->events(...year(2026));

    expect($events->pluck('type')->all())->toBe([HistoryEventType::Published, HistoryEventType::Updated]);
});

it('does not claim a draft was ever published', function () {
    makeCollection('blog');
    makeEntry('entry-1', attributes: ['date' => '2026-03-04', 'published' => false]);

    expect((new EntryDataHistorySource)->events(...year(2026)))->toBeEmpty();
});

it('does not claim a future-dated entry has gone out yet', function () {
    makeCollection('blog');
    makeEntry('entry-1', attributes: ['date' => CarbonImmutable::now()->addYear()->format('Y-m-d')]);

    $to = CarbonImmutable::now()->addYears(2);

    expect((new EntryDataHistorySource)->events(CarbonImmutable::now()->subYear(), $to))->toBeEmpty();
});

it('takes the first author when the blueprint allows several', function () {
    makeCollection('blog');
    makeEntry('entry-1', ['author' => ['user-2', 'user-3']], ['date' => '2026-03-04']);

    expect((new EntryDataHistorySource)->events(...year(2026))->sole()->author)->toBe('user-2');
});

it('leaves the author null when the blueprint has none', function () {
    makeCollection('blog');
    makeEntry('entry-1', attributes: ['date' => '2026-03-04']);

    expect((new EntryDataHistorySource)->events(...year(2026))->sole()->author)->toBeNull();
});

it('ignores an updated_at that is not a timestamp', function () {
    makeCollection('pages', dated: false);
    makeEntry('page-1', ['updated_at' => 'yesterday'], ['collection' => 'pages']);

    expect((new EntryDataHistorySource)->events(...year(2026)))->toBeEmpty();
});

it('only returns events inside the window', function () {
    makeCollection('blog');
    makeEntry('old', attributes: ['date' => '2025-12-31']);
    makeEntry('inside', attributes: ['date' => '2026-07-01']);
    makeEntry('later', attributes: ['date' => '2027-01-01']);

    expect((new EntryDataHistorySource)->events(...year(2026))->pluck('itemId')->all())->toBe(['inside']);
});

it('returns events oldest first', function () {
    makeCollection('blog');
    makeEntry('third', attributes: ['date' => '2026-09-01']);
    makeEntry('first', attributes: ['date' => '2026-01-05']);
    makeEntry('second', attributes: ['date' => '2026-04-02']);

    expect((new EntryDataHistorySource)->events(...year(2026))->pluck('itemId')->all())
        ->toBe(['first', 'second', 'third']);
});

it('filters by site when asked', function () {
    useSites();
    makeCollection('blog', sites: ['default', 'french']);
    makeEntry('en', attributes: ['date' => '2026-03-04', 'site' => 'default']);
    makeEntry('fr', attributes: ['date' => '2026-03-05', 'site' => 'french']);

    $source = new EntryDataHistorySource;

    expect($source->events(...[...year(2026), 'french'])->pluck('itemId')->all())->toBe(['fr'])
        ->and($source->events(...year(2026))->pluck('itemId')->all())->toBe(['en', 'fr']);
});

it('reports the earliest event, for the first-year framing', function () {
    makeCollection('blog');
    makeEntry('entry-1', attributes: ['date' => '2024-06-10']);
    makeEntry('entry-2', attributes: ['date' => '2026-01-01']);

    expect((new EntryDataHistorySource)->earliestEvent()?->toDateString())->toBe('2024-06-10');
});

it('reports the earliest event for one site', function () {
    useSites();
    makeCollection('blog', sites: ['default', 'french']);
    makeEntry('en', attributes: ['date' => '2024-06-10', 'site' => 'default']);
    makeEntry('fr', attributes: ['date' => '2026-02-11', 'site' => 'french']);

    expect((new EntryDataHistorySource)->earliestEvent('french')?->toDateString())->toBe('2026-02-11');
});
