<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\ResolvedHistory;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Stats\StatContext;
use Bpmore\Wrapped\Tests\Fixtures\FakeHistorySource;
use Bpmore\Wrapped\Tests\TestCase;
use Carbon\CarbonImmutable;

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Shared card helpers
|--------------------------------------------------------------------------
|
| Declared here rather than per file: Pest test files share one global
| namespace, so a helper defined in two of them is a fatal redeclaration.
| Names are prefixed for the same reason — `context()` and `event()` are
| already taken by Laravel's own helpers.
|
*/

function wrappedEvent(
    HistoryEventType $type,
    string $id,
    string $at,
    string $itemType = 'entry',
    ?string $author = null,
    ?string $site = null,
    array $meta = [],
): HistoryEvent {
    return new HistoryEvent(
        type: $type,
        itemType: $itemType,
        itemId: $id,
        occurredAt: CarbonImmutable::parse($at),
        author: $author,
        site: $site,
        meta: $meta,
    );
}

function wasPublished(string $id, string $at, string $itemType = 'entry', ?string $author = null): HistoryEvent
{
    return wrappedEvent(HistoryEventType::Published, $id, $at, $itemType, $author);
}

function wasUpdated(string $id, string $at, string $itemType = 'entry', ?string $author = null): HistoryEvent
{
    return wrappedEvent(HistoryEventType::Updated, $id, $at, $itemType, $author);
}

function wasCreated(string $id, string $at, string $itemType = 'entry', ?string $author = null): HistoryEvent
{
    return wrappedEvent(HistoryEventType::Created, $id, $at, $itemType, $author);
}

/**
 * A context over a fake source holding exactly the given events.
 *
 * @param  list<HistoryEvent>  $events
 */
function cardContext(
    array $events,
    Period $period = Period::Year,
    ?string $from = null,
    ?string $to = null,
    Confidence $confidence = Confidence::High,
    ?string $site = null,
): StatContext {
    return new StatContext(
        history: ResolvedHistory::for(new FakeHistorySource('logbook', $confidence, events: $events)),
        period: $period,
        from: CarbonImmutable::parse($from ?? '2026-01-01 00:00:00'),
        to: CarbonImmutable::parse($to ?? '2026-12-31 23:59:59'),
        site: $site,
    );
}
