<?php

use Bpmore\Wrapped\History\Sources\EntryDataHistorySource;
use Bpmore\Wrapped\History\Sources\LogbookHistorySource;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;

/**
 * SPEC.md §4: "Aggregating across 40,000 entries on page load will not do."
 * These prove the generate command copes with a large site, through real
 * sources rather than fakes, and stays inside a budget that would be noticed
 * if it regressed.
 *
 * The numbers are deliberately not tuned to the edge. A slow CI box should
 * still pass; a real regression (loading everything into memory, reading the
 * log once per card) should still fail.
 */
uses(RefreshDatabase::class);

const LARGE_ROWS = 40000;

const LARGE_ENTRIES = 1500;

function logbookOf(int $rows): void
{
    $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
    config(['logbook.db.connection' => $connection, 'database.connections.logbook' => $connection]);

    Schema::connection('logbook')->create(LogbookHistorySource::TABLE, function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('action', 100)->index();
        $table->string('user_id', 36)->nullable();
        $table->string('user_email', 191)->nullable();
        $table->string('subject_type', 50);
        $table->string('subject_id', 191)->nullable();
        $table->string('subject_handle', 191)->nullable();
        $table->string('subject_title', 191)->nullable();
        $table->json('changes')->nullable();
        $table->json('meta')->nullable();
        $table->string('ip', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamp('created_at')->useCurrent()->index();
    });

    $start = CarbonImmutable::parse('2025-01-01 00:00:00');
    $actions = ['statamic.entry.created', 'statamic.entry.published', 'statamic.entry.updated', 'statamic.entry.updated'];
    $authors = ['ada', 'grace', 'linus', 'margaret'];
    $meta = json_encode(['site' => 'default', 'collection' => 'blog']);

    $batch = [];
    for ($i = 0; $i < $rows; $i++) {
        $batch[] = [
            'action' => $actions[$i % 4],
            // Per entry, not per row: otherwise every "published" row lands on
            // the same author and the team card rightly declines.
            'user_id' => $authors[intdiv($i, 4) % 4],
            'subject_type' => 'entry',
            'subject_id' => 'entry-'.intdiv($i, 4),
            'subject_handle' => 'entry-'.intdiv($i, 4),
            'subject_title' => 'Entry '.intdiv($i, 4),
            'meta' => $meta,
            // Spread over two years so both this period and the previous one
            // have plenty in them.
            'created_at' => $start->addMinutes($i * 26)->toDateTimeString(),
        ];

        if (count($batch) === 500) {
            DB::connection('logbook')->table(LogbookHistorySource::TABLE)->insert($batch);
            $batch = [];
        }
    }

    if ($batch !== []) {
        DB::connection('logbook')->table(LogbookHistorySource::TABLE)->insert($batch);
    }
}

function contentOf(int $entries): void
{
    $content = sys_get_temp_dir().'/wrapped-large-'.bin2hex(random_bytes(6));
    mkdir($content.'/collections', 0777, true);
    test()->content = $content;

    Stache::store('collections')->directory($content.'/collections');
    Stache::store('entries')->directory($content.'/collections');
    Stache::clear();

    // Search indexing reads back every entry on each save; not what this
    // test is measuring.
    config(['statamic.search.indexes' => []]);

    Collection::make('blog')->dated(true)->sites(['default'])->save();

    $start = CarbonImmutable::parse('2026-01-01 09:00:00');

    for ($i = 0; $i < $entries; $i++) {
        Entry::make()
            ->collection('blog')
            ->id("entry-{$i}")
            ->slug("entry-{$i}")
            ->locale('default')
            ->published(true)
            ->date($start->addHours($i * 5))
            ->data(['title' => "Entry {$i}", 'updated_at' => $start->addHours($i * 5 + 3)->getTimestamp(), 'updated_by' => 'ada'])
            ->save();
    }

    Stache::clear();
}

beforeEach(fn () => CarbonImmutable::setTestNow('2026-12-01 09:00:00'));

afterEach(function () {
    CarbonImmutable::setTestNow();

    if (isset($this->content)) {
        exec('rm -rf '.escapeshellarg($this->content));
    }
});

it('builds a wrapped from forty thousand audit rows inside a sensible budget', function () {
    logbookOf(LARGE_ROWS);

    $started = hrtime(true);
    $before = memory_get_peak_usage(true);

    $this->artisan('wrapped:generate')->assertSuccessful();

    $seconds = (hrtime(true) - $started) / 1e9;
    $megabytes = (memory_get_peak_usage(true) - $before) / 1048576;

    $snapshot = Snapshot::sole();

    expect($snapshot->history_source)->toBe('logbook')
        ->and($snapshot->stats['entries_published']['count'])->toBeGreaterThan(4000)
        ->and($snapshot->stats['entries_published']['previous'])->toBeGreaterThan(4000)
        // Not most_revised: that card resolves the entry, and this fixture has
        // rows but no entries on disk, so it correctly comes back empty.
        ->and($snapshot->stats['busiest_month'])->toHaveKey('month')
        ->and($snapshot->stats['people']['people'])->toBe(4)
        ->and($seconds)->toBeLessThan(30.0, "generate took {$seconds}s")
        ->and($megabytes)->toBeLessThan(256.0, "generate grew memory by {$megabytes}MB");
})->group('scenario');

it('reads the log once per window, not once per card', function () {
    logbookOf(2000);

    DB::connection('logbook')->enableQueryLog();

    $this->artisan('wrapped:generate')->assertSuccessful();

    $reads = collect(DB::connection('logbook')->getQueryLog())
        ->filter(fn (array $query) => str_starts_with(strtolower($query['query']), 'select'))
        ->count();

    // One "does the table exist", then: the period, the previous period, the
    // all-time window for the untouched card, and one earliest-event probe.
    // Sixteen cards reading independently would be well past this, and so
    // would checking the table once per read, which is what this caught.
    expect($reads)->toBeLessThanOrEqual(6, "{$reads} reads of the audit log");
})->group('scenario');

it('walks fifteen hundred real entries without loading them all at once', function () {
    contentOf(LARGE_ENTRIES);

    $started = hrtime(true);

    $this->artisan('wrapped:generate')->assertSuccessful();

    $seconds = (hrtime(true) - $started) / 1e9;

    $snapshot = Snapshot::sole();

    expect($snapshot->history_source)->toBe('entry_data')
        ->and($snapshot->stats['entries_published']['count'])->toBe(LARGE_ENTRIES)
        ->and($seconds)->toBeLessThan(60.0, "generate took {$seconds}s");

    // Chunked iteration, not one giant get(): the source reads through the
    // query builder's lazy() and never holds every entry at once.
    expect((new EntryDataHistorySource)->isAvailable())->toBeTrue();
})->group('scenario');
