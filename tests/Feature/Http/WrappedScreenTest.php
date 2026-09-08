<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

function asAdmin(): void
{
    test()->actingAs(User::make()->id('admin')->email('admin@example.com')->makeSuper());
}

/**
 * @param  array<string, mixed>  $stats
 */
function storeSnapshot(array $stats, Confidence $confidence = Confidence::High): Snapshot
{
    return Snapshot::create([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => $confidence,
        'stats' => $stats,
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
        'generated_by' => null,
    ]);
}

beforeEach(fn () => asAdmin());

it('renders the wrapped screen', function () {
    storeSnapshot(['entries_published' => ['count' => 42, 'previous' => null]]);

    $this->get(cp_route('wrapped.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('wrapped::Wrapped')
            ->where('site', 'default')
            ->where('snapshot.periodKey', '2026')
            ->where('snapshot.confidence', 'high')
            ->where('snapshot.historySource', 'logbook')
            ->has('snapshot.cards', 1)
        );
});

it('turns a stored card into a heading and a sentence', function () {
    storeSnapshot(['entries_published' => ['count' => 1234, 'previous' => null]]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('snapshot.cards.0.handle', 'entries_published')
            ->where('snapshot.cards.0.heading', 'Entries published')
            ->where('snapshot.cards.0.body', 'You published 1,234 entries.')
        );
});

it('reads differently when there is a period to compare against', function () {
    storeSnapshot(['entries_published' => ['count' => 42, 'previous' => 31]]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('snapshot.cards.0.body', 'You published 42 entries, against 31 the period before.')
        );
});

it('says plainly when nothing has been generated', function () {
    $this->get(cp_route('wrapped.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('snapshot', null));
});

it('still renders a wrapped whose cards were all left out', function () {
    storeSnapshot([], Confidence::Low);

    $this->get(cp_route('wrapped.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('snapshot.confidence', 'low')
            ->has('snapshot.cards', 0)
        );
});

it('shows the most recent snapshot for the site', function () {
    storeSnapshot(['entries_published' => ['count' => 1, 'previous' => null]]);

    Snapshot::create([
        'site' => 'default',
        'period' => Period::Quarter,
        'period_key' => '2026-Q4',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => ['entries_published' => ['count' => 9, 'previous' => null]],
        'generated_at' => CarbonImmutable::parse('2026-12-05 09:00:00'),
        'generated_by' => null,
    ]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('snapshot.periodKey', '2026-Q4'));
});

it('leaves out a card it has no wording for, rather than printing a translation key', function () {
    storeSnapshot([
        'entries_published' => ['count' => 4, 'previous' => null],
        'something_new' => ['count' => 2],
    ]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('snapshot.cards', 1)
            ->where('snapshot.cards.0.handle', 'entries_published')
        );
});
