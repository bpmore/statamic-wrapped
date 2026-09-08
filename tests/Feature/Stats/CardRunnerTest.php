<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\CardRunner;
use Bpmore\Wrapped\Stats\StatCardRegistry;
use Bpmore\Wrapped\Stats\StatsResult;
use Bpmore\Wrapped\Tests\Fixtures\FakeStatCard;
use Illuminate\Support\Facades\Log;

function runCards(array $cards, Confidence $confidence = Confidence::High): StatsResult
{
    return (new CardRunner(new StatCardRegistry($cards)))
        ->run(cardContext([], confidence: $confidence));
}

it('computes a card the history can support', function () {
    $result = runCards([new FakeStatCard('entries_published', Confidence::Partial, ['count' => 42])]);

    expect($result->stats)->toBe(['entries_published' => ['count' => 42]])
        ->and($result->omitted)->toBe([])
        ->and($result->wasLimited())->toBeFalse();
});

it('never even computes a card the history cannot support', function () {
    $card = new FakeStatCard('assets_uploaded', Confidence::High);

    $result = runCards([$card], confidence: Confidence::Partial);

    expect($result->stats)->toBe([])
        ->and($result->omitted)->toBe(['assets_uploaded'])
        ->and($card->computed)->toBe(0);
});

it('tells a card left out for want of history apart from one with nothing to say', function () {
    $result = runCards([
        new FakeStatCard('needs_logbook', Confidence::High),
        new FakeStatCard('quiet_year', Confidence::Partial, null),
        new FakeStatCard('worked', Confidence::Partial, ['count' => 1]),
    ], confidence: Confidence::Partial);

    expect($result->omitted)->toBe(['needs_logbook'])
        ->and($result->empty)->toBe(['quiet_year'])
        ->and($result->stats)->toBe(['worked' => ['count' => 1]])
        ->and($result->wasLimited())->toBeTrue();
});

it('leaves nothing out when the history is good enough for everything', function () {
    $result = runCards([
        new FakeStatCard('a', Confidence::High),
        new FakeStatCard('b', Confidence::Partial),
        new FakeStatCard('c', Confidence::Low),
    ]);

    expect(array_keys($result->stats))->toBe(['a', 'b', 'c'])
        ->and($result->wasLimited())->toBeFalse();
});

it('leaves out everything that needs more than file mtimes', function () {
    $result = runCards([
        new FakeStatCard('needs_logbook', Confidence::High),
        new FakeStatCard('needs_dates', Confidence::Partial),
        new FakeStatCard('needs_nothing', Confidence::Low),
    ], confidence: Confidence::Low);

    expect($result->omitted)->toBe(['needs_logbook', 'needs_dates'])
        ->and(array_keys($result->stats))->toBe(['needs_nothing']);
});

it('records a card that throws and carries on with the rest', function () {
    Log::spy();

    $result = runCards([
        new FakeStatCard('first', Confidence::Low, ['ok' => 1]),
        new FakeStatCard('broken', Confidence::Low, throws: 'that entry was odd'),
        new FakeStatCard('last', Confidence::Low, ['ok' => 2]),
    ]);

    expect($result->failed)->toBe(['broken'])
        ->and(array_keys($result->stats))->toBe(['first', 'last']);

    Log::shouldHaveReceived('warning')->once();
});

it('reports an empty wrapped as empty', function () {
    expect(runCards([new FakeStatCard('a', Confidence::Low, null)])->isEmpty())->toBeTrue()
        ->and(runCards([new FakeStatCard('a', Confidence::Low, ['x' => 1])])->isEmpty())->toBeFalse();
});

it('runs with no cards at all', function () {
    $result = runCards([]);

    expect($result->isEmpty())->toBeTrue()
        ->and($result->wasLimited())->toBeFalse();
});

describe('over the cards this addon actually ships', function () {
    it('leaves out every card on a site with only file mtimes', function () {
        $result = (new CardRunner(app(StatCardRegistry::class)))
            ->run(cardContext([
                wasUpdated('a', '2026-02-01 09:00:00'),
                wasUpdated('b', '2026-03-01 09:00:00'),
            ], confidence: Confidence::Low));

        expect($result->stats)->toBe([])
            ->and($result->wasLimited())->toBeTrue()
            ->and($result->omitted)->toContain('entries_published', 'busiest_month', 'assets_uploaded');
    });

    it('leaves out only the logbook-grade cards on a partial site', function () {
        $result = (new CardRunner(app(StatCardRegistry::class)))
            ->run(cardContext([
                wasPublished('a', '2026-02-01 09:00:00', author: 'ada'),
                wasPublished('b', '2026-03-01 09:00:00', author: 'grace'),
            ], confidence: Confidence::Partial));

        expect($result->omitted)->toBe(['assets_uploaded', 'fastest_turnaround'])
            ->and($result->stats)->toHaveKey('entries_published');
    });

    it('computes every card it can on a logbook site', function () {
        $result = (new CardRunner(app(StatCardRegistry::class)))
            ->run(cardContext([
                wasPublished('a', '2026-02-01 09:00:00', author: 'ada'),
                wasPublished('b', '2026-03-01 09:00:00', author: 'grace'),
            ], confidence: Confidence::High));

        expect($result->omitted)->toBe([])
            ->and($result->failed)->toBe([]);
    });
});
