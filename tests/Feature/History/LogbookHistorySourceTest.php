<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\Sources\LogbookHistorySource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logbook is not a dependency of this addon, so these tests stand up a table
 * with its schema instead. The schema mirrors Logbook v2's install command; if
 * it drifts, this is the file that has to notice.
 */
function logbookConnection(): void
{
    $config = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];

    config([
        'logbook.db.connection' => $config,
        'database.connections.logbook' => $config,
    ]);
}

function createLogbookTable(): void
{
    Schema::connection('logbook')->create(LogbookHistorySource::TABLE, function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('action', 100)->index();
        $table->string('user_id', 36)->nullable()->index();
        $table->string('user_email', 191)->nullable()->index();
        $table->string('subject_type', 50)->index();
        $table->string('subject_id', 191)->nullable()->index();
        $table->string('subject_handle', 191)->nullable()->index();
        $table->string('subject_title', 191)->nullable();
        $table->json('changes')->nullable();
        $table->json('meta')->nullable();
        $table->string('ip', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamp('created_at')->useCurrent()->index();
    });
}

/**
 * @param  array<string, mixed>  $attributes
 */
function logAudit(array $attributes = []): void
{
    // Note the array_key_exists: an explicit null meta is a case worth testing.
    $meta = array_key_exists('meta', $attributes) ? $attributes['meta'] : ['site' => 'default'];

    DB::connection('logbook')->table(LogbookHistorySource::TABLE)->insert(array_merge([
        'action' => 'statamic.entry.published',
        'user_id' => 'user-1',
        'user_email' => 'ed@example.com',
        'subject_type' => 'entry',
        'subject_id' => 'entry-1',
        'subject_handle' => 'hello-world',
        'subject_title' => 'Hello World',
        'changes' => null,
        'created_at' => '2026-03-04 09:00:00',
    ], $attributes, ['meta' => $meta === null ? null : json_encode($meta)]));
}

function wholeOf(int $year): array
{
    return [
        CarbonImmutable::create($year, 1, 1)->startOfDay(),
        CarbonImmutable::create($year, 12, 31)->endOfDay(),
    ];
}

beforeEach(function () {
    logbookConnection();
});

it('is unavailable when the logbook table is missing', function () {
    $source = new LogbookHistorySource;

    expect($source->isAvailable())->toBeFalse()
        ->and($source->events(...wholeOf(2026)))->toBeEmpty()
        ->and($source->earliestEvent())->toBeNull();
});

it('is unavailable when the site has no logbook config', function () {
    config(['logbook.db.connection' => null]);

    expect((new LogbookHistorySource)->isAvailable())->toBeFalse();
});

it('is available once the logbook table exists', function () {
    createLogbookTable();

    $source = new LogbookHistorySource;

    expect($source->isAvailable())->toBeTrue()
        ->and($source->confidence())->toBe(Confidence::High)
        ->and($source->handle())->toBe('logbook')
        ->and($source->name())->toBe('Statamic Logbook');
});

it('reads an audit row as a history event', function () {
    createLogbookTable();
    logAudit(['meta' => ['site' => 'default', 'collection' => 'blog', 'uri' => '/hello-world']]);

    $event = (new LogbookHistorySource)->events(...wholeOf(2026))->sole();

    expect($event->type)->toBe(HistoryEventType::Published)
        ->and($event->itemType)->toBe('entry')
        ->and($event->itemId)->toBe('entry-1')
        ->and($event->itemKey())->toBe('entry::entry-1')
        ->and($event->author)->toBe('user-1')
        ->and($event->site)->toBe('default')
        ->and($event->occurredAt->toDateTimeString())->toBe('2026-03-04 09:00:00')
        ->and($event->meta)->toBe([
            'action' => 'statamic.entry.published',
            'collection' => 'blog',
            'uri' => '/hello-world',
            'title' => 'Hello World',
        ]);
});

it('maps every logbook operation it can use', function (string $action, HistoryEventType $expected) {
    createLogbookTable();
    logAudit(['action' => $action]);

    expect((new LogbookHistorySource)->events(...wholeOf(2026))->sole()->type)->toBe($expected);
})->with([
    ['statamic.entry.created', HistoryEventType::Created],
    ['statamic.asset.uploaded', HistoryEventType::Created],
    ['statamic.entry.published', HistoryEventType::Published],
    ['statamic.entry.updated', HistoryEventType::Updated],
    ['statamic.entry.saved', HistoryEventType::Updated],
    ['statamic.asset.replaced', HistoryEventType::Updated],
    ['statamic.entry.unpublished', HistoryEventType::Updated],
    ['statamic.entry.deleted', HistoryEventType::Deleted],
]);

it('drops rows it cannot honestly place', function (array $attributes) {
    createLogbookTable();
    logAudit($attributes);

    expect((new LogbookHistorySource)->events(...wholeOf(2026)))->toBeEmpty();
})->with([
    'a sign-in is audit signal, not history' => [['action' => 'statamic.user.login', 'subject_type' => 'user']],
    'an unmapped operation' => [['action' => 'statamic.entry.event']],
    'no subject to attach it to' => [['subject_id' => null, 'subject_handle' => null]],
]);

it('falls back to the subject handle when there is no id', function () {
    createLogbookTable();
    logAudit(['subject_id' => null, 'subject_handle' => 'blog']);

    expect((new LogbookHistorySource)->events(...wholeOf(2026))->sole()->itemId)->toBe('blog');
});

it('leaves site null when logbook did not record one', function () {
    createLogbookTable();
    logAudit(['meta' => null]);

    $event = (new LogbookHistorySource)->events(...wholeOf(2026))->sole();

    expect($event->site)->toBeNull()
        ->and($event->meta)->not->toHaveKey('collection');
});

it('only returns events inside the window', function () {
    createLogbookTable();
    logAudit(['subject_id' => 'old', 'created_at' => '2025-12-31 23:59:59']);
    logAudit(['subject_id' => 'inside', 'created_at' => '2026-07-01 12:00:00']);
    logAudit(['subject_id' => 'later', 'created_at' => '2027-01-01 00:00:01']);

    $events = (new LogbookHistorySource)->events(...wholeOf(2026));

    expect($events->pluck('itemId')->all())->toBe(['inside']);
});

it('filters by site when asked', function () {
    createLogbookTable();
    logAudit(['subject_id' => 'en', 'meta' => ['site' => 'default']]);
    logAudit(['subject_id' => 'fr', 'meta' => ['site' => 'french']]);

    $source = new LogbookHistorySource;

    expect($source->events(...[...wholeOf(2026), 'french'])->pluck('itemId')->all())->toBe(['fr'])
        ->and($source->events(...wholeOf(2026))->pluck('itemId')->all())->toBe(['en', 'fr']);
});

it('returns events oldest first', function () {
    createLogbookTable();
    logAudit(['subject_id' => 'third', 'created_at' => '2026-09-01 08:00:00']);
    logAudit(['subject_id' => 'first', 'created_at' => '2026-01-05 08:00:00']);
    logAudit(['subject_id' => 'second', 'created_at' => '2026-04-02 08:00:00']);

    expect((new LogbookHistorySource)->events(...wholeOf(2026))->pluck('itemId')->all())
        ->toBe(['first', 'second', 'third']);
});

it('reports the earliest usable event', function () {
    createLogbookTable();
    logAudit(['action' => 'statamic.user.login', 'created_at' => '2024-02-02 08:00:00']);
    logAudit(['created_at' => '2025-06-10 08:00:00']);
    logAudit(['created_at' => '2026-01-01 08:00:00']);

    expect((new LogbookHistorySource)->earliestEvent()?->toDateTimeString())->toBe('2025-06-10 08:00:00');
});

it('reports the earliest event for one site', function () {
    createLogbookTable();
    logAudit(['created_at' => '2025-06-10 08:00:00', 'meta' => ['site' => 'default']]);
    logAudit(['created_at' => '2026-02-11 08:00:00', 'meta' => ['site' => 'french']]);

    expect((new LogbookHistorySource)->earliestEvent('french')?->toDateTimeString())->toBe('2026-02-11 08:00:00');
});

it('reports no earliest event on an empty table', function () {
    createLogbookTable();

    expect((new LogbookHistorySource)->earliestEvent())->toBeNull();
});
