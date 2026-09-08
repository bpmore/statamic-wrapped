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
