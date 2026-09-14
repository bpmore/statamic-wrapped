<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Tests\Fixtures\FakeHistorySource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Role;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

/**
 * The resolver over a fake source, so the button can be pressed without a
 * logbook table or a content tree. Same shape as the command's tests.
 */
function cpHistoryOf(array $events, Confidence $confidence = Confidence::High, bool $available = true): void
{
    app()->instance(HistorySourceResolver::class, new HistorySourceResolver([
        new FakeHistorySource('logbook', $confidence, available: $available, events: $events),
    ]));
}

function cpAdmin(): void
{
    test()->actingAs(User::make()->id('admin')->email('admin@example.com')->makeSuper());
}

/**
 * A logged-in user who is not a super, holding exactly these permissions.
 *
 * @param  list<string>  $permissions
 */
function cpUserWith(array $permissions): void
{
    Role::make('builder')->title('Builder')->permissions($permissions)->save();

    test()->actingAs(User::make()->id('builder')->email('builder@example.com')->assignRole('builder'));
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-14 09:00:00');

    $this->roles = sys_get_temp_dir().'/wrapped-roles-'.bin2hex(random_bytes(6));
    mkdir($this->roles, 0777, true);
    config(['statamic.users.repositories.file.paths.roles' => $this->roles.'/roles.yaml']);
    Stache::clear();

    cpHistoryOf([
        wasPublished('a', '2026-02-03 14:00:00', author: 'ada'),
        wasPublished('b', '2026-05-04 14:00:00', author: 'grace'),
        wasPublished('c', '2026-06-05 09:00:00', author: 'ada'),
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('builds a year from the screen and lands on it', function () {
    cpAdmin();

    $this->post(cp_route('wrapped.generate'), ['period' => 'year', 'year' => 2026])
        ->assertRedirect(cp_route('wrapped.index', ['period' => '2026']))
        ->assertSessionHasNoErrors();

    $snapshot = Snapshot::sole();

    expect($snapshot->site)->toBe('default')
        ->and($snapshot->period)->toBe(Period::Year)
        ->and($snapshot->period_key)->toBe('2026')
        ->and($snapshot->stats['entries_published']['count'])->toBe(3)
        ->and($snapshot->generated_by)->toBe('admin');
});

it('builds a quarter', function () {
    cpAdmin();

    $this->post(cp_route('wrapped.generate'), ['period' => 'quarter', 'year' => 2026, 'part' => 2])
        ->assertRedirect(cp_route('wrapped.index', ['period' => '2026-Q2']));

    expect(Snapshot::sole()->period_key)->toBe('2026-Q2')
        ->and(Snapshot::sole()->stats['entries_published']['count'])->toBe(2);
});

it('builds a month', function () {
    cpAdmin();

    $this->post(cp_route('wrapped.generate'), ['period' => 'month', 'year' => 2026, 'part' => 6])
        ->assertRedirect(cp_route('wrapped.index', ['period' => '2026-06']));

    expect(Snapshot::sole()->period_key)->toBe('2026-06')
        ->and(Snapshot::sole()->stats['entries_published']['count'])->toBe(1);
});

it('rebuilds in place rather than adding a second row', function () {
    cpAdmin();

    $this->post(cp_route('wrapped.generate'), ['period' => 'year', 'year' => 2026]);
    $first = Snapshot::sole()->generated_at;

    CarbonImmutable::setTestNow('2026-09-15 09:00:00');
    $this->post(cp_route('wrapped.generate'), ['period' => 'year', 'year' => 2026]);

    expect(Snapshot::count())->toBe(1)
        ->and(Snapshot::sole()->generated_at->greaterThan($first))->toBeTrue();
});

it('refuses a quarter that is not one', function (mixed $part) {
    cpAdmin();

    $this->from(cp_route('wrapped.index'))
        ->post(cp_route('wrapped.generate'), ['period' => 'quarter', 'year' => 2026, 'part' => $part])
        ->assertRedirect(cp_route('wrapped.index'))
        ->assertSessionHasErrors('part');

    expect(Snapshot::count())->toBe(0);
})->with([0, 5, null, 'summer']);

it('refuses a month that is not one', function (mixed $part) {
    cpAdmin();

    $this->from(cp_route('wrapped.index'))
        ->post(cp_route('wrapped.generate'), ['period' => 'month', 'year' => 2026, 'part' => $part])
        ->assertSessionHasErrors('part');

    expect(Snapshot::count())->toBe(0);
})->with([0, 13, null]);

it('refuses a part on a year', function () {
    cpAdmin();

    $this->from(cp_route('wrapped.index'))
        ->post(cp_route('wrapped.generate'), ['period' => 'year', 'year' => 2026, 'part' => 3])
        ->assertSessionHasErrors('part');
});

it('refuses a year it cannot build and a period it does not know', function () {
    cpAdmin();

    $this->from(cp_route('wrapped.index'))
        ->post(cp_route('wrapped.generate'), ['period' => 'year', 'year' => 20260])
        ->assertSessionHasErrors('year');

    $this->from(cp_route('wrapped.index'))
        ->post(cp_route('wrapped.generate'), ['period' => 'decade', 'year' => 2026])
        ->assertSessionHasErrors('period');
});

it('says so when there is no readable history, rather than writing an empty wrapped', function () {
    cpAdmin();
    cpHistoryOf([], available: false);

    $this->from(cp_route('wrapped.index'))
        ->post(cp_route('wrapped.generate'), ['period' => 'year', 'year' => 2026])
        ->assertSessionHasErrors('period');

    expect(Snapshot::count())->toBe(0);
});

it('needs its own permission, not just view', function () {
    cpUserWith(['access cp', CardGate::VIEW]);

    // The control panel turns an authorization failure into a redirect to
    // the dashboard with an error, which is what the person sees.
    $this->post(cp_route('wrapped.generate'), ['period' => 'year', 'year' => 2026])
        ->assertRedirect(cp_route('index'))
        ->assertSessionHas('error', 'This action is unauthorized.');

    expect(Snapshot::count())->toBe(0);
});

it('is allowed with the permission', function () {
    cpUserWith(['access cp', CardGate::VIEW, CardGate::GENERATE]);

    $this->post(cp_route('wrapped.generate'), ['period' => 'year', 'year' => 2026])
        ->assertRedirect(cp_route('wrapped.index', ['period' => '2026']));

    expect(Snapshot::sole()->generated_by)->toBe('builder');
});

it('offers the form on the screen only to someone who may build', function () {
    cpUserWith(['access cp', CardGate::VIEW]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('generate', null));

    cpUserWith(['access cp', CardGate::VIEW, CardGate::GENERATE]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generate.url', cp_route('wrapped.generate'))
            ->where('generate.year', 2026)
            ->where('generate.quarter', 3)
            ->where('generate.month', 9)
        );
});
