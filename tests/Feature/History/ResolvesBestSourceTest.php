<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\History\Sources\LogbookHistorySource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Statamic\Facades\Collection as Collections;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;

/**
 * The whole of phase 1, end to end: four real sources over real fixtures, and
 * the one the resolver settles on. Each test adds a better source than the last.
 */
function resolver(): HistorySourceResolver
{
    return app(HistorySourceResolver::class);
}

function givenEntries(bool $dated = false, array $data = []): void
{
    Collections::make('blog')->dated($dated)->sites(['default'])->save();

    $entry = Entry::make()
        ->collection('blog')
        ->id('entry-1')
        ->slug('hello')
        ->locale('default')
        ->published(true)
        ->data(array_merge(['title' => 'Hello World'], $data));

    if ($dated) {
        $entry->date('2026-03-04');
    }

    $entry->save();

    touch(Entry::find('entry-1')->path(), CarbonImmutable::parse('2026-04-04 09:00:00')->getTimestamp());

    Stache::clear();
}

function givenRevisions(): void
{
    $directory = Stache::store('revisions')->directory().'collections/blog/default/entry-1';

    mkdir($directory, 0777, true);

    file_put_contents($directory.'/'.CarbonImmutable::parse('2026-05-05 09:00:00')->getTimestamp().'.yaml', YAML::dump([
        'action' => 'publish',
        'date' => CarbonImmutable::parse('2026-05-05 09:00:00')->getTimestamp(),
        'user' => 'user-1',
        'attributes' => ['data' => ['title' => 'Hello World']],
    ]));
}

function givenLogbook(): void
{
    Schema::connection('logbook')->create(LogbookHistorySource::TABLE, function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('action', 100);
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
        $table->timestamp('created_at')->useCurrent();
    });
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/wrapped-resolve-'.bin2hex(random_bytes(6));

    mkdir($this->root.'/collections', 0777, true);
    mkdir($this->root.'/revisions', 0777, true);

    Stache::store('collections')->directory($this->root.'/collections');
    Stache::store('entries')->directory($this->root.'/collections');
    Stache::store('revisions')->directory($this->root.'/revisions');
    Stache::clear();

    // Logbook's connection is configured but its table is not created, so the
    // source is correctly unavailable until a test asks for it.
    $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];

    config([
        'logbook.db.connection' => $connection,
        'database.connections.logbook' => $connection,
    ]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->root));
});

it('registers every source, best first', function () {
    expect(resolver()->sources()->map->handle()->all())
        ->toBe(['logbook', 'revisions', 'entry_data', 'mtime']);
});

it('is the same resolver every time it is asked for', function () {
    expect(resolver())->toBe(resolver());
});

it('resolves to nothing on a site with no history at all', function () {
    $resolved = resolver()->resolve();

    expect($resolved)->toBeNull()
        ->and(resolver()->confidence())->toBe(Confidence::Low);
});

it('falls back to mtime when entries carry no dates of their own', function () {
    givenEntries();

    $resolved = resolver()->resolve();

    expect($resolved?->handle())->toBe('mtime')
        ->and($resolved?->name())->toBe('File modification times')
        ->and($resolved?->confidence)->toBe(Confidence::Low);
});

it('prefers entry data over mtime once entries have dates', function () {
    givenEntries(dated: true);

    $resolved = resolver()->resolve();

    expect($resolved?->handle())->toBe('entry_data')
        ->and($resolved?->confidence)->toBe(Confidence::Partial);
});

it('prefers revisions over entry data, both being partial', function () {
    givenEntries(dated: true);
    givenRevisions();

    expect(resolver()->resolve()?->handle())->toBe('revisions');
});

it('prefers logbook over everything else', function () {
    givenEntries(dated: true);
    givenRevisions();
    givenLogbook();

    $resolved = resolver()->resolve();

    expect($resolved?->handle())->toBe('logbook')
        ->and($resolved?->name())->toBe('Statamic Logbook')
        ->and($resolved?->confidence)->toBe(Confidence::High);
});

it('picks logbook even when it is the only source there is', function () {
    givenLogbook();

    expect(resolver()->resolve()?->handle())->toBe('logbook');
});

it('reports what the resolved source can and cannot support', function () {
    givenEntries(dated: true);

    $resolved = resolver()->resolve();

    expect($resolved?->supports(Confidence::Low))->toBeTrue()
        ->and($resolved?->supports(Confidence::Partial))->toBeTrue()
        ->and($resolved?->supports(Confidence::High))->toBeFalse();
});

it('reads history through whichever source it resolved', function () {
    givenEntries(dated: true);
    givenRevisions();

    $source = resolver()->resolve()?->source;

    $events = $source->events(
        CarbonImmutable::create(2026, 1, 1)->startOfDay(),
        CarbonImmutable::create(2026, 12, 31)->endOfDay(),
    );

    expect($events->sole()->occurredAt->toDateTimeString())->toBe('2026-05-05 09:00:00')
        ->and($source->earliestEvent()?->toDateTimeString())->toBe('2026-05-05 09:00:00');
});
