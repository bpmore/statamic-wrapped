<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\Sources\RevisionsHistorySource;
use Carbon\CarbonImmutable;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;

/**
 * Revisions live on disk, so these tests build a real revisions directory in the
 * layout Statamic writes: collections/<collection>/<site>/<entry id>/<timestamp>.yaml
 */
function revisionsDirectory(): string
{
    return Stache::store('revisions')->directory();
}

/**
 * @param  array<string, mixed>  $revision
 */
function writeRevision(string $id, int $timestamp, array $revision = [], string $collection = 'blog', string $site = 'default'): void
{
    $directory = sprintf('%scollections/%s/%s/%s', revisionsDirectory(), $collection, $site, $id);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($directory.'/'.$timestamp.'.yaml', YAML::dump(array_merge([
        'action' => 'revision',
        'date' => $timestamp,
        'user' => 'user-1',
        'message' => null,
        'attributes' => [
            'id' => $id,
            'slug' => 'hello-world',
            'published' => true,
            'date' => null,
            'data' => ['title' => 'Hello World'],
        ],
    ], $revision)));
}

function writeRawRevision(string $id, string $filename, string $contents, string $collection = 'blog', string $site = 'default'): void
{
    $directory = sprintf('%scollections/%s/%s/%s', revisionsDirectory(), $collection, $site, $id);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($directory.'/'.$filename, $contents);
}

function at(string $date): int
{
    return CarbonImmutable::parse($date)->getTimestamp();
}

function wholeYear(int $year): array
{
    return [
        CarbonImmutable::create($year, 1, 1)->startOfDay(),
        CarbonImmutable::create($year, 12, 31)->endOfDay(),
    ];
}

beforeEach(function () {
    $this->revisions = sys_get_temp_dir().'/wrapped-revisions-'.bin2hex(random_bytes(6));

    mkdir($this->revisions, 0777, true);

    Stache::store('revisions')->directory($this->revisions);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->revisions));
});

it('is unavailable when nothing has been revised', function () {
    $source = new RevisionsHistorySource;

    expect($source->isAvailable())->toBeFalse()
        ->and($source->events(...wholeYear(2026)))->toBeEmpty()
        ->and($source->earliestEvent())->toBeNull();
});

it('is available once a revision exists', function () {
    writeRevision('entry-1', at('2026-03-04 09:00:00'));

    $source = new RevisionsHistorySource;

    expect($source->isAvailable())->toBeTrue()
        ->and($source->handle())->toBe('revisions')
        ->and($source->name())->toBe('Statamic Revisions');
});

it('is rated partial, because revisions are opt-in per collection', function () {
    expect((new RevisionsHistorySource)->confidence())->toBe(Confidence::Partial);
});

it('reads a revision file as a history event', function () {
    writeRevision('entry-1', at('2026-03-04 09:00:00'), ['message' => 'Fixed the typo']);

    $event = (new RevisionsHistorySource)->events(...wholeYear(2026))->sole();

    expect($event->type)->toBe(HistoryEventType::Updated)
        ->and($event->itemType)->toBe('entry')
        ->and($event->itemId)->toBe('entry-1')
        ->and($event->author)->toBe('user-1')
        ->and($event->site)->toBe('default')
        ->and($event->occurredAt->toDateTimeString())->toBe('2026-03-04 09:00:00')
        ->and($event->meta)->toBe([
            'action' => 'revision',
            'collection' => 'blog',
            'message' => 'Fixed the typo',
            'title' => 'Hello World',
        ]);
});

it('maps every revision action', function (string $action, HistoryEventType $expected) {
    writeRevision('entry-1', at('2026-03-04 09:00:00'), ['action' => $action]);

    expect((new RevisionsHistorySource)->events(...wholeYear(2026))->sole()->type)->toBe($expected);
})->with([
    ['publish', HistoryEventType::Published],
    ['unpublish', HistoryEventType::Updated],
    ['revision', HistoryEventType::Updated],
]);

it('skips the working copy, which is a draft rather than something that happened', function () {
    writeRawRevision('entry-1', 'working.yaml', YAML::dump([
        'action' => 'working',
        'date' => at('2026-05-05 09:00:00'),
        'user' => 'user-1',
        'attributes' => [],
    ]));

    $source = new RevisionsHistorySource;

    expect($source->isAvailable())->toBeFalse()
        ->and($source->events(...wholeYear(2026)))->toBeEmpty();
});

it('skips a revision file it cannot read', function () {
    writeRawRevision('entry-1', at('2026-03-04 09:00:00').'.yaml', "action: revision\nattributes: [unclosed");

    expect((new RevisionsHistorySource)->events(...wholeYear(2026)))->toBeEmpty();
});

it('skips an action it does not recognise', function () {
    writeRevision('entry-1', at('2026-03-04 09:00:00'), ['action' => 'something-new']);

    expect((new RevisionsHistorySource)->events(...wholeYear(2026)))->toBeEmpty();
});

it('treats a revision with no action as an ordinary revision', function () {
    writeRawRevision('entry-1', at('2026-03-04 09:00:00').'.yaml', YAML::dump([
        'date' => at('2026-03-04 09:00:00'),
        'user' => null,
    ]));

    $event = (new RevisionsHistorySource)->events(...wholeYear(2026))->sole();

    expect($event->type)->toBe(HistoryEventType::Updated)
        ->and($event->author)->toBeNull();
});

it('only returns events inside the window', function () {
    writeRevision('old', at('2025-12-31 23:59:59'));
    writeRevision('inside', at('2026-07-01 12:00:00'));
    writeRevision('later', at('2027-01-01 00:00:01'));

    expect((new RevisionsHistorySource)->events(...wholeYear(2026))->pluck('itemId')->all())
        ->toBe(['inside']);
});

it('trusts the recorded date over the filename when they disagree', function () {
    writeRevision('entry-1', at('2026-07-01 12:00:00'), ['date' => at('2020-01-01 00:00:00')]);

    expect((new RevisionsHistorySource)->events(...wholeYear(2026)))->toBeEmpty();
});

it('filters by site when asked', function () {
    writeRevision('en', at('2026-03-04 09:00:00'), site: 'default');
    writeRevision('fr', at('2026-03-05 09:00:00'), site: 'french');

    $source = new RevisionsHistorySource;

    expect($source->events(...[...wholeYear(2026), 'french'])->pluck('itemId')->all())->toBe(['fr'])
        ->and($source->events(...wholeYear(2026))->pluck('itemId')->all())->toBe(['en', 'fr']);
});

it('returns events oldest first, across collections', function () {
    writeRevision('third', at('2026-09-01 08:00:00'), collection: 'pages');
    writeRevision('first', at('2026-01-05 08:00:00'), collection: 'blog');
    writeRevision('second', at('2026-04-02 08:00:00'), collection: 'pages');

    expect((new RevisionsHistorySource)->events(...wholeYear(2026))->pluck('itemId')->all())
        ->toBe(['first', 'second', 'third']);
});

it('reports the earliest revision', function () {
    writeRevision('entry-1', at('2024-06-10 08:00:00'));
    writeRevision('entry-2', at('2026-01-01 08:00:00'));

    expect((new RevisionsHistorySource)->earliestEvent()?->toDateTimeString())->toBe('2024-06-10 08:00:00');
});

it('reports the earliest revision for one site', function () {
    writeRevision('entry-1', at('2024-06-10 08:00:00'), site: 'default');
    writeRevision('entry-2', at('2026-02-11 08:00:00'), site: 'french');

    expect((new RevisionsHistorySource)->earliestEvent('french')?->toDateTimeString())->toBe('2026-02-11 08:00:00');
});

it('does not let an unusable revision set the earliest date', function () {
    writeRawRevision('entry-1', at('2020-01-01 00:00:00').'.yaml', "action: revision\nattributes: [unclosed");
    writeRevision('entry-2', at('2026-02-11 08:00:00'));

    expect((new RevisionsHistorySource)->earliestEvent()?->toDateTimeString())->toBe('2026-02-11 08:00:00');
});
