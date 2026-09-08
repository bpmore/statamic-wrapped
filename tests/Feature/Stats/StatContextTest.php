<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\ResolvedHistory;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Stats\StatCardRegistry;
use Bpmore\Wrapped\Stats\StatContext;
use Bpmore\Wrapped\Tests\Fixtures\FakeHistorySource;
use Carbon\CarbonImmutable;

function anEvent(string $id, string $at, ?string $site = 'default'): HistoryEvent
{
    return new HistoryEvent(
        type: HistoryEventType::Published,
        itemType: 'entry',
        itemId: $id,
        occurredAt: CarbonImmutable::parse($at),
        site: $site,
    );
}

function contextOver(FakeHistorySource $source, ?string $site = null): StatContext
{
    return new StatContext(
        history: ResolvedHistory::for($source),
        period: Period::Year,
        from: CarbonImmutable::create(2026, 1, 1)->startOfDay(),
        to: CarbonImmutable::create(2026, 12, 31)->endOfDay(),
        site: $site,
    );
}

it('hands cards the events for its period', function () {
    $context = contextOver(new FakeHistorySource('logbook', Confidence::High, events: [
        anEvent('a', '2025-06-01 09:00:00'),
        anEvent('b', '2026-06-01 09:00:00'),
    ]));

    expect($context->events()->pluck('itemId')->all())->toBe(['b']);
});

it('reads each window from the source only once', function () {
    $source = new FakeHistorySource('logbook', Confidence::High, events: [
        anEvent('b', '2026-06-01 09:00:00'),
    ]);

    $context = contextOver($source);

    $context->events();
    $context->events();
    $context->events();

    expect($source->reads)->toBe(1);
});

it('reads another window when a card asks for one, and caches that too', function () {
    $source = new FakeHistorySource('logbook', Confidence::High, events: [
        anEvent('a', '2025-06-01 09:00:00'),
        anEvent('b', '2026-06-01 09:00:00'),
    ]);

    $context = contextOver($source);

    $lastYear = [CarbonImmutable::create(2025, 1, 1)->startOfDay(), CarbonImmutable::create(2025, 12, 31)->endOfDay()];

    expect($context->events()->pluck('itemId')->all())->toBe(['b'])
        ->and($context->events(...$lastYear)->pluck('itemId')->all())->toBe(['a'])
        ->and($context->events(...$lastYear)->pluck('itemId')->all())->toBe(['a'])
        ->and($source->reads)->toBe(2);
});

it('passes its site through to the source', function () {
    $source = new FakeHistorySource('logbook', Confidence::High, events: [
        anEvent('en', '2026-06-01 09:00:00', 'default'),
        anEvent('fr', '2026-06-02 09:00:00', 'french'),
    ]);

    expect(contextOver($source, 'french')->events()->pluck('itemId')->all())->toBe(['fr']);
});

it('reports the confidence cards are gated on', function () {
    $context = contextOver(new FakeHistorySource('mtime', Confidence::Low));

    expect($context->confidence())->toBe(Confidence::Low)
        ->and($context->history->handle())->toBe('mtime');
});

it('registers a stat card registry in the container', function () {
    expect(app(StatCardRegistry::class))->toBeInstanceOf(StatCardRegistry::class)
        ->and(app(StatCardRegistry::class))->toBe(app(StatCardRegistry::class));
});
