<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Tests\Fixtures\FakeHistorySource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\User;

/**
 * The first-year problem from SPEC.md §1. A site installed three months ago has
 * no year, and must say "since June" rather than pretend to a 2026.
 */
uses(RefreshDatabase::class);

function siteThatBegan(string $date, array $events): void
{
    app()->instance(HistorySourceResolver::class, new HistorySourceResolver([
        new FakeHistorySource('logbook', Confidence::High, events: [
            wasPublished('first', $date, author: 'ada'),
            ...$events,
        ]),
    ]));
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-13 09:00:00');
    $this->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper());
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('records when a young site began', function () {
    siteThatBegan('2026-06-10 09:00:00', [
        wasPublished('b', '2026-07-01 09:00:00', author: 'ada'),
        wasPublished('c', '2026-08-15 09:00:00', author: 'grace'),
    ]);

    $this->artisan('wrapped:generate')->assertSuccessful();

    $snapshot = Snapshot::sole();

    expect($snapshot->isFirstPeriod())->toBeTrue()
        ->and($snapshot->started_at->toDateString())->toBe('2026-06-10');
});

it('records nothing for a site older than the period', function () {
    siteThatBegan('2024-02-10 09:00:00', [
        wasPublished('b', '2026-07-01 09:00:00', author: 'ada'),
    ]);

    $this->artisan('wrapped:generate')->assertSuccessful();

    expect(Snapshot::sole()->isFirstPeriod())->toBeFalse()
        ->and(Snapshot::sole()->started_at)->toBeNull();
});

it('frames the screen as "since June" rather than "2026"', function () {
    siteThatBegan('2026-06-10 09:00:00', [
        wasPublished('b', '2026-07-01 09:00:00', author: 'ada'),
    ]);

    $this->artisan('wrapped:generate')->assertSuccessful();

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('snapshot.label', 'Since June 2026')
            ->where('snapshot.periodKey', '2026'));
});

it('frames an old site with its plain period', function () {
    siteThatBegan('2024-02-10 09:00:00', [
        wasPublished('b', '2026-07-01 09:00:00', author: 'ada'),
    ]);

    $this->artisan('wrapped:generate')->assertSuccessful();

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('snapshot.label', '2026'));
});

it('does not compare a young site against a year it did not have', function () {
    siteThatBegan('2026-06-10 09:00:00', [
        wasPublished('b', '2026-07-01 09:00:00', author: 'ada'),
        wasPublished('c', '2026-08-15 09:00:00', author: 'grace'),
    ]);

    $this->artisan('wrapped:generate')->assertSuccessful();

    $stats = Snapshot::sole()->stats;

    // Three published, and "previous" is null rather than 0: the site did not
    // publish nothing last year, it did not exist.
    expect($stats['entries_published'])->toBe(['count' => 3, 'previous' => null])
        // Nothing can have gone quiet before the site had spoken.
        ->and($stats)->not->toHaveKey('collection_went_quiet');
});

it('does not mistake recently installed history for a young site', function () {
    // Logbook went in this June, but the entries themselves go back years.
    // That is an old site with new history, and the entry dates say so.
    app()->instance(HistorySourceResolver::class, new HistorySourceResolver([
        new FakeHistorySource('logbook', Confidence::High, events: [
            wasPublished('new', '2026-06-10 09:00:00', author: 'ada'),
        ]),
        new FakeHistorySource('entry_data', Confidence::Partial, events: [
            wasPublished('ancient', '2019-03-03 00:00:00'),
        ]),
    ]));

    $this->artisan('wrapped:generate')->assertSuccessful();

    expect(Snapshot::sole()->isFirstPeriod())->toBeFalse();
});

it('does not let file mtimes make a deployed site look brand new', function () {
    // Every file was written by last week's deploy. That is not when the site
    // began, and mtimes are not trusted to say so.
    app()->instance(HistorySourceResolver::class, new HistorySourceResolver([
        new FakeHistorySource('entry_data', Confidence::Partial, events: [
            wasPublished('old', '2023-01-01 00:00:00'),
        ]),
        new FakeHistorySource('mtime', Confidence::Low, events: [
            wasUpdated('old', '2026-09-06 03:00:00'),
        ]),
    ]));

    $this->artisan('wrapped:generate')->assertSuccessful();

    expect(Snapshot::sole()->isFirstPeriod())->toBeFalse();
});
