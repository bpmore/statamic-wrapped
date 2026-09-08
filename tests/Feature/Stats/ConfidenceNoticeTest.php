<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\ConfidenceNotice;
use Bpmore\Wrapped\Stats\StatCardRegistry;
use Bpmore\Wrapped\Tests\Fixtures\FakeStatCard;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $stats
 */
function noticeFor(string $source, Confidence $confidence, array $stats = ['entries_published' => ['count' => 3]]): array
{
    $snapshot = new Snapshot([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => $source,
        'confidence' => $confidence,
        'stats' => $stats,
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
    ]);

    return app(ConfidenceNotice::class)->for($snapshot);
}

it('names the source rather than printing its handle', function (string $handle, string $name) {
    expect(noticeFor($handle, Confidence::High)['sourceName'])->toBe($name);
})->with([
    ['logbook', 'Statamic Logbook'],
    ['revisions', 'Statamic revisions'],
    ['entry_data', 'the dates on your entries'],
    ['mtime', 'file modification times'],
]);

it('copes with a source it has never heard of', function () {
    expect(noticeFor('something_new', Confidence::High)['sourceName'])->toBe('an unknown source');
});

it('says quietly what a complete wrapped was built from', function () {
    $notice = noticeFor('logbook', Confidence::High);

    expect($notice['notice'])->toBe('Built from Statamic Logbook.')
        ->and($notice['limited'])->toBeFalse()
        ->and($notice['omitted'])->toBe(0)
        ->and($notice['suggestion'])->toBeNull();
});

it('says how many cards were left out on a partial wrapped', function () {
    $notice = noticeFor('revisions', Confidence::Partial);

    // The two cards that need Logbook: assets_uploaded and fastest_turnaround.
    expect($notice['omitted'])->toBe(2)
        ->and($notice['limited'])->toBeTrue()
        ->and($notice['notice'])
        ->toBe('Built from Statamic revisions, which is limited history. 2 cards were left out rather than guessed at.');
});

it('says "one card", not "1 cards"', function () {
    // A registry with a single card, so exactly one is out of reach.
    $registry = new StatCardRegistry([
        new FakeStatCard('needs_logbook', Confidence::High),
    ]);

    app()->instance(StatCardRegistry::class, $registry);

    expect(noticeFor('revisions', Confidence::Partial)['notice'])
        ->toBe('Built from Statamic revisions, which is limited history. One card was left out rather than guessed at.');
});

it('explains itself plainly when it could say nothing at all', function () {
    $notice = noticeFor('mtime', Confidence::Low, stats: []);

    expect($notice['notice'])
        ->toBe('There is not enough history here to say anything yet. This site has only file modification times to go on, and most deploys rewrite those.')
        ->and($notice['limited'])->toBeTrue();
});

it('suggests logbook where it would help', function () {
    expect(noticeFor('mtime', Confidence::Low, stats: [])['suggestion'])
        ->toContain('Statamic Logbook records who changed what');
});

it('does not nag a wrapped that is already complete', function () {
    expect(noticeFor('logbook', Confidence::High)['suggestion'])->toBeNull();
});

it('does not suggest logbook to a site already running it', function () {
    // Logbook installed but its history only reaches partway.
    expect(noticeFor('logbook', Confidence::Partial)['suggestion'])->toBeNull();
});

it('works the omitted count out from the cards, not from stored state', function () {
    // Nothing about which cards were skipped is stored on the snapshot; the
    // answer comes from what each card needs against what the snapshot had.
    expect(noticeFor('mtime', Confidence::Low, stats: [])['omitted'])
        ->toBe(app(StatCardRegistry::class)->all()->count());
});
