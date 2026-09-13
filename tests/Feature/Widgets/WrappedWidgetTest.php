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

it('links to the wrapped screen', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 1, 'previous' => null]]);

    expect(widgetProps()['url'])->toBe(cp_route('wrapped.index'));
});

it('renders through the wrapped-widget vue component', function () {
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
    widgetSnapshot(['entries_published' => ['count' => 1, 'previous' => null]]);

    expect(app(WrappedWidget::class)->component()->toArray()['name'])->toBe('wrapped-widget');
});
