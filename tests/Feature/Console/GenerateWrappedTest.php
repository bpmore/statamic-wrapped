<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Tests\Fixtures\FakeHistorySource;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Facades\Site;

uses(RefreshDatabase::class);

/**
 * Swap the container's resolver for one over a fake source, so the command can
 * be driven without standing up a logbook table or a content tree.
 *
 * @param  list<HistoryEvent>  $events
 */
function historyOf(array $events, Confidence $confidence = Confidence::High, bool $available = true): void
{
    app()->instance(HistorySourceResolver::class, new HistorySourceResolver([
        new FakeHistorySource('logbook', $confidence, available: $available, events: $events),
    ]));
}

function aYearOfWork(): array
{
    return [
        wasPublished('a', '2026-02-03 14:00:00', author: 'ada'),
        wasPublished('b', '2026-02-04 14:00:00', author: 'grace'),
        wasPublished('c', '2026-08-05 09:00:00', author: 'ada'),
    ];
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-12-01 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('builds a snapshot for the current year by default', function () {
    historyOf(aYearOfWork());

    $this->artisan('wrapped:generate')->assertSuccessful();

    $snapshot = Snapshot::sole();

    expect($snapshot->site)->toBe('default')
        ->and($snapshot->period)->toBe(Period::Year)
        ->and($snapshot->period_key)->toBe('2026')
        ->and($snapshot->history_source)->toBe('logbook')
        ->and($snapshot->confidence)->toBe(Confidence::High)
        ->and($snapshot->stats)->toHaveKey('entries_published')
        ->and($snapshot->stats['entries_published']['count'])->toBe(3);
});

it('builds a named year', function () {
    historyOf([...aYearOfWork(), wasPublished('old', '2025-05-05 09:00:00')]);

    $this->artisan('wrapped:generate', ['--year' => 2025])->assertSuccessful();

    expect(Snapshot::sole()->period_key)->toBe('2025')
        ->and(Snapshot::sole()->stats['entries_published']['count'])->toBe(1);
});

it('builds a single quarter', function () {
    historyOf(aYearOfWork());

    $this->artisan('wrapped:generate', ['--year' => 2026, '--quarter' => 1])->assertSuccessful();

    $snapshot = Snapshot::sole();

    expect($snapshot->period)->toBe(Period::Quarter)
        ->and($snapshot->period_key)->toBe('2026-Q1')
        ->and($snapshot->stats['entries_published']['count'])->toBe(2);
});

it('refuses a quarter that is not one', function (string $quarter) {
    historyOf(aYearOfWork());

    $this->artisan('wrapped:generate', ['--quarter' => $quarter])->assertExitCode(Command::INVALID);

    expect(Snapshot::count())->toBe(0);
})->with(['0', '5', 'autumn']);

it('refuses a site it does not have', function () {
    historyOf(aYearOfWork());

    $this->artisan('wrapped:generate', ['--site' => 'atlantis'])->assertExitCode(Command::INVALID);

    expect(Snapshot::count())->toBe(0);
});

it('fails when there is no readable history at all', function () {
    historyOf([], available: false);

    $this->artisan('wrapped:generate')->assertExitCode(Command::FAILURE);

    expect(Snapshot::count())->toBe(0);
});

it('leaves an existing snapshot alone', function () {
    historyOf(aYearOfWork());

    $this->artisan('wrapped:generate')->assertSuccessful();

    $first = Snapshot::sole();

    CarbonImmutable::setTestNow('2026-12-02 09:00:00');

    $this->artisan('wrapped:generate')
        ->expectsOutputToContain('already built')
        ->assertSuccessful();

    expect(Snapshot::count())->toBe(1)
        ->and(Snapshot::sole()->generated_at->toDateTimeString())->toBe($first->generated_at->toDateTimeString());
});

it('rebuilds in place when forced, rather than adding a second row', function () {
    historyOf(aYearOfWork());

    $this->artisan('wrapped:generate')->assertSuccessful();

    CarbonImmutable::setTestNow('2026-12-02 09:00:00');
    historyOf([...aYearOfWork(), wasPublished('d', '2026-09-09 09:00:00', author: 'ada')]);

    $this->artisan('wrapped:generate', ['--force' => true])->assertSuccessful();

    expect(Snapshot::count())->toBe(1)
        ->and(Snapshot::sole()->stats['entries_published']['count'])->toBe(4)
        ->and(Snapshot::sole()->generated_at->toDateTimeString())->toBe('2026-12-02 09:00:00');
});

it('builds one snapshot per site', function () {
    config(['statamic.system.multisite' => true]);

    Site::setSites([
        'default' => ['name' => 'Default', 'url' => '/', 'locale' => 'en_US'],
        'french' => ['name' => 'French', 'url' => '/fr/', 'locale' => 'fr_FR'],
    ]);

    historyOf([
        wasPublished('a', '2026-02-03 14:00:00', site: 'default'),
        wasPublished('b', '2026-02-04 14:00:00', site: 'default'),
        wasPublished('c', '2026-08-05 09:00:00', site: 'french'),
    ]);

    $this->artisan('wrapped:generate')->assertSuccessful();

    expect(Snapshot::count())->toBe(2)
        ->and(Snapshot::where('site', 'default')->sole()->stats['entries_published']['count'])->toBe(2)
        ->and(Snapshot::where('site', 'french')->sole()->stats['entries_published']['count'])->toBe(1);
});

it('builds only the site it was asked for', function () {
    config(['statamic.system.multisite' => true]);

    Site::setSites([
        'default' => ['name' => 'Default', 'url' => '/', 'locale' => 'en_US'],
        'french' => ['name' => 'French', 'url' => '/fr/', 'locale' => 'fr_FR'],
    ]);

    historyOf([wasPublished('c', '2026-08-05 09:00:00', site: 'french')]);

    $this->artisan('wrapped:generate', ['--site' => 'french'])->assertSuccessful();

    expect(Snapshot::count())->toBe(1)
        ->and(Snapshot::sole()->site)->toBe('french');
});

it('records what the wrapped was built from, not what is installed later', function () {
    historyOf(aYearOfWork(), confidence: Confidence::Low);

    $this->artisan('wrapped:generate')->assertSuccessful();

    $snapshot = Snapshot::sole();

    expect($snapshot->confidence)->toBe(Confidence::Low)
        ->and($snapshot->supports(Confidence::Partial))->toBeFalse()
        // Every card needs more than file mtimes, so an mtime-grade wrapped is
        // empty. The row is still written: the CP needs it to explain why.
        ->and($snapshot->stats)->toBe([]);
});

it('says how many cards were left out for want of better history', function () {
    historyOf(aYearOfWork(), confidence: Confidence::Partial);

    $this->artisan('wrapped:generate')
        ->expectsOutputToContain('left out (history too limited)')
        ->assertSuccessful();
});

it('has no generated_by for an unattended run', function () {
    historyOf(aYearOfWork());

    $this->artisan('wrapped:generate')->assertSuccessful();

    expect(Snapshot::sole()->generated_by)->toBeNull();
});
