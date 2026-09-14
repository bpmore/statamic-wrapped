<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Sharing\Share;
use Bpmore\Wrapped\Sharing\ShareLinks;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

/*
 * The default: sharing off. This is what a fresh install looks like, and
 * what SPEC.md §5 promised — no public URL unless the site asks for one.
 */

it('is off by default', function () {
    expect(config('wrapped.share.enabled'))->toBeFalse()
        ->and(app(ShareLinks::class)->enabled())->toBeFalse();
});

it('serves nothing at the public route when off, even for a real link', function () {
    $share = Share::create([
        'token' => str_repeat('a', 40),
        'site' => 'default',
        'period_key' => '2026',
        'label' => '2026',
        'cards' => [['handle' => 'entries_published', 'heading' => 'Entries published', 'body' => 'You published 42 entries.']],
        'people' => false,
        'created_at' => CarbonImmutable::now(),
    ]);

    expect($share->isLive())->toBeTrue();

    $this->get(route('wrapped.share.show', ['token' => $share->token]))->assertNotFound();
});

it('offers no sharing on the screen, even to a super', function () {
    $this->actingAs(User::make()->id('admin')->email('admin@example.com')->makeSuper());
    Snapshot::create([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => ['entries_published' => ['count' => 42, 'previous' => null]],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
        'generated_by' => null,
    ]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('snapshot.share', null));
});

it('refuses to make a link, even for a super', function () {
    $this->actingAs(User::make()->id('admin')->email('admin@example.com')->makeSuper());
    Snapshot::create([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => ['entries_published' => ['count' => 42, 'previous' => null]],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
        'generated_by' => null,
    ]);

    $this->post(cp_route('wrapped.share.store'), ['period' => '2026'])->assertForbidden();

    expect(Share::count())->toBe(0);
});
