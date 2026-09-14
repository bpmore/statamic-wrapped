<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function snapshot(array $attributes = []): Snapshot
{
    return Snapshot::create(array_merge([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => ['entries_published' => 42],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
        'generated_by' => 'user-1',
    ], $attributes));
}

it('creates the table when the host site migrates', function () {
    expect(Schema::hasTable('wrapped_snapshots'))->toBeTrue()
        ->and(Schema::hasColumns('wrapped_snapshots', [
            'id', 'site', 'period', 'period_key', 'history_source',
            'confidence', 'stats', 'generated_at', 'generated_by',
        ]))->toBeTrue();
});

it('stores and reads back a snapshot', function () {
    snapshot();

    $stored = Snapshot::sole();

    expect($stored->site)->toBe('default')
        ->and($stored->period)->toBe(Period::Year)
        ->and($stored->period_key)->toBe('2026')
        ->and($stored->history_source)->toBe('logbook')
        ->and($stored->confidence)->toBe(Confidence::High)
        ->and($stored->stats)->toBe(['entries_published' => 42])
        ->and($stored->generated_at->toDateTimeString())->toBe('2026-12-01 09:00:00')
        ->and($stored->generated_by)->toBe('user-1');
});

it('keeps the stats as structured data, not a string', function () {
    snapshot(['stats' => ['busiest' => ['day' => 'Tuesday', 'hour' => 14]]]);

    expect(Snapshot::sole()->stats)->toBe(['busiest' => ['day' => 'Tuesday', 'hour' => 14]]);
});

it('remembers the confidence of the run that made it', function () {
    $stored = snapshot(['history_source' => 'mtime', 'confidence' => Confidence::Low]);

    expect($stored->supports(Confidence::Low))->toBeTrue()
        ->and($stored->supports(Confidence::Partial))->toBeFalse()
        ->and($stored->supports(Confidence::High))->toBeFalse();
});

it('has no generated_by for an unattended run', function () {
    expect(snapshot(['generated_by' => null])->generated_by)->toBeNull();
});

it('allows one snapshot per site per period', function () {
    snapshot();

    expect(fn () => snapshot())->toThrow(UniqueConstraintViolationException::class);
});

it('keeps a separate snapshot for each site', function () {
    snapshot(['site' => 'default']);
    snapshot(['site' => 'french']);

    expect(Snapshot::count())->toBe(2);
});

it('keeps a separate snapshot for each period of the same year', function () {
    snapshot(['period' => Period::Year, 'period_key' => '2026']);
    snapshot(['period' => Period::Quarter, 'period_key' => '2026-Q3']);
    snapshot(['period' => Period::Quarter, 'period_key' => '2026-Q4']);

    expect(Snapshot::count())->toBe(3);
});

it('builds a year key', function () {
    expect(Period::Year->key(2026))->toBe('2026')
        ->and(Period::Year->key(2026, 3))->toBe('2026');
});

it('builds a quarter key', function () {
    expect(Period::Quarter->key(2026, 3))->toBe('2026-Q3');
});

it('refuses a quarterly key without a usable quarter', function (?int $quarter) {
    expect(fn () => Period::Quarter->key(2026, $quarter))->toThrow(InvalidArgumentException::class);
})->with([null, 0, 5]);

it('builds a month key with a leading zero, so keys sort as text', function () {
    expect(Period::Month->key(2026, 9))->toBe('2026-09')
        ->and(Period::Month->key(2026, 12))->toBe('2026-12');
});

it('refuses a monthly key without a usable month', function (?int $month) {
    expect(fn () => Period::Month->key(2026, $month))->toThrow(InvalidArgumentException::class);
})->with([null, 0, 13]);

it('bounds a month from its first midnight to its last second', function () {
    [$from, $to] = Period::Month->window(2026, 2);

    expect($from->format('Y-m-d H:i:s'))->toBe('2026-02-01 00:00:00')
        ->and($to->format('Y-m-d H:i:s'))->toBe('2026-02-28 23:59:59');
});

it('bounds a quarter and a year the same way', function () {
    [$from, $to] = Period::Quarter->window(2026, 4);
    [$yearFrom, $yearTo] = Period::Year->window(2026);

    expect($from->format('Y-m-d H:i:s'))->toBe('2026-10-01 00:00:00')
        ->and($to->format('Y-m-d H:i:s'))->toBe('2026-12-31 23:59:59')
        ->and($yearFrom->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00')
        ->and($yearTo->format('Y-m-d H:i:s'))->toBe('2026-12-31 23:59:59');
});

it('steps a month back to the whole previous month, not to thirty-one days earlier', function () {
    [$from, $to] = Period::Month->window(2026, 3);
    [$previousFrom, $previousTo] = Period::Month->previous($from, $to);

    // March has 31 days and February 28: subtracting the window's length
    // would start the previous window on 29 January.
    expect($previousFrom->format('Y-m-d H:i:s'))->toBe('2026-02-01 00:00:00')
        ->and($previousTo->format('Y-m-d H:i:s'))->toBe('2026-02-28 23:59:59');
});

it('steps January back into the previous year', function () {
    [$from, $to] = Period::Month->window(2026, 1);
    [$previousFrom, $previousTo] = Period::Month->previous($from, $to);

    expect($previousFrom->format('Y-m-d'))->toBe('2025-12-01')
        ->and($previousTo->format('Y-m-d'))->toBe('2025-12-31');
});

it('reads a key back into its parts', function (string $key, Period $period, int $year, ?int $part) {
    expect(Period::fromKey($key))->toBe([$period, $year, $part]);
})->with([
    ['2026', Period::Year, 2026, null],
    ['2026-Q3', Period::Quarter, 2026, 3],
    ['2026-09', Period::Month, 2026, 9],
]);

it('does not read a key it never wrote', function (string $key) {
    expect(Period::fromKey($key))->toBeNull();
})->with(['2026-9', '2026-13', '2026-Q5', 'september', '']);

it('knows the day a snapshot starts on, from its key alone', function () {
    expect(snapshot(['period' => Period::Month, 'period_key' => '2026-09'])->startsOn()->format('Y-m-d'))->toBe('2026-09-01')
        ->and(snapshot(['period' => Period::Quarter, 'period_key' => '2026-Q3'])->startsOn()->format('Y-m-d'))->toBe('2026-07-01')
        ->and(snapshot(['period_key' => '2025'])->startsOn()->format('Y-m-d'))->toBe('2025-01-01');
});

it('turns every kind of period key into words', function (string $key, string $label) {
    expect(Period::label($key))->toBe($label);
})->with([
    ['2026', '2026'],
    ['2026-Q3', 'Q3 2026'],
    ['2026-09', 'September 2026'],
    ['2026-01', 'January 2026'],
]);
