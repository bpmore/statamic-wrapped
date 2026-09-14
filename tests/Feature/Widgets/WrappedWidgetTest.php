<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Widgets\WrappedWidget;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $stats
 */
function widgetSnapshot(array $stats = [], string $key = '2026', Period $period = Period::Year, Confidence $confidence = Confidence::High): void
{
    Snapshot::create([
        'site' => 'default',
        'period' => $period,
        'period_key' => $key,
        'history_source' => $confidence === Confidence::Low ? 'mtime' : 'logbook',
        'confidence' => $confidence,
        'stats' => $stats,
        'generated_at' => CarbonImmutable::now(),
        'generated_by' => null,
    ]);
}

function widgetProps(): ?array
{
    $component = app(WrappedWidget::class)->component();

    return $component?->toArray()['props'];
}

// The widget shows nothing to someone who cannot open the screen, so every
// test here views as a super user unless it says otherwise.
beforeEach(fn () => test()->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper()));

afterEach(fn () => CarbonImmutable::setTestNow());

it('shows nothing at all when there is no wrapped', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');

    expect(app(WrappedWidget::class)->component())->toBeNull();
});

it('nudges in december, for the year that is ending', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 42, 'previous' => null]]);

    $props = widgetProps();

    expect($props['nudge'])->toBeTrue()
        ->and($props['title'])->toBe('Your 2026 Wrapped is ready.')
        ->and($props['headline'])->toBe('You published 42 entries.')
        ->and($props['key'])->toBe('2026');
});

it('is a quiet link the rest of the year', function () {
    CarbonImmutable::setTestNow('2026-06-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 42, 'previous' => null]]);

    $props = widgetProps();

    expect($props['nudge'])->toBeFalse()
        ->and($props['title'])->toBe('Your 2026 Wrapped');
});

it('does not nudge in december about a previous year', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 42, 'previous' => null]], key: '2025');

    expect(widgetProps()['nudge'])->toBeFalse();
});

it('does not nudge about a quarter', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(
        ['entries_published' => ['count' => 42, 'previous' => null]],
        key: '2026-Q4',
        period: Period::Quarter,
    );

    expect(widgetProps()['nudge'])->toBeFalse();
});

it('names a month or quarter in words, not by its key', function () {
    CarbonImmutable::setTestNow('2026-10-05 09:00:00');
    widgetSnapshot(
        ['entries_published' => ['count' => 42, 'previous' => null]],
        key: '2026-09',
        period: Period::Month,
    );

    expect(widgetProps()['title'])->toBe('Your September 2026 Wrapped')
        ->and(widgetProps()['nudge'])->toBeFalse();
});

it('leads with the published count, which is the fact people came for', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot([
        'busiest_month' => ['year' => 2026, 'month' => 7, 'count' => 12],
        'entries_published' => ['count' => 42, 'previous' => null],
    ]);

    expect(widgetProps()['headline'])->toBe('You published 42 entries.');
});

it('falls back to whatever the wrapped can say', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(['busiest_month' => ['year' => 2026, 'month' => 7, 'count' => 12]]);

    expect(widgetProps()['headline'])->toBe('July 2026 was your busiest month, with 12 entries.');
});

it('explains itself rather than showing an empty line on a thin site', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot([], confidence: Confidence::Low);

    expect(widgetProps()['headline'])->toContain('not enough history here');
});

it('links to its own period on the wrapped screen', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 1, 'previous' => null]]);

    expect(widgetProps()['url'])->toBe(cp_route('wrapped.index', ['period' => '2026']));
});

it('shows the year in December even when a month was built after it', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 40, 'previous' => null]]);
    Snapshot::where('period_key', '2026')->update(['generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00')]);
    widgetSnapshot(['entries_published' => ['count' => 3, 'previous' => null]], key: '2026-11', period: Period::Month);
    Snapshot::where('period_key', '2026-11')->update(['generated_at' => CarbonImmutable::parse('2026-12-02 09:00:00')]);

    expect(widgetProps()['title'])->toBe('Your 2026 Wrapped is ready.')
        ->and(widgetProps()['nudge'])->toBeTrue();
});

it('shows the newest build outside December', function () {
    CarbonImmutable::setTestNow('2026-10-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 40, 'previous' => null]]);
    Snapshot::where('period_key', '2026')->update(['generated_at' => CarbonImmutable::parse('2026-10-01 09:00:00')]);
    widgetSnapshot(['entries_published' => ['count' => 3, 'previous' => null]], key: '2026-09', period: Period::Month);
    Snapshot::where('period_key', '2026-09')->update(['generated_at' => CarbonImmutable::parse('2026-10-02 09:00:00')]);

    expect(widgetProps()['title'])->toBe('Your September 2026 Wrapped');
});

it('renders through the wrapped-widget vue component', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 1, 'previous' => null]]);

    expect(app(WrappedWidget::class)->component()->toArray()['name'])->toBe('wrapped-widget');
});
