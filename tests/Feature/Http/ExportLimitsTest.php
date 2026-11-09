<?php

use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\Export\RenderLock;
use Bpmore\Wrapped\Export\VideoRenderer;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Bpmore\Wrapped\Tests\Fixtures\FakeVideoRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

/**
 * An image or a video is a browser launch on the request thread, asked
 * for by GET by anyone who may view the Wrapped. Two limits keep that from
 * being a way to pin the server: one render at a time per site, and a
 * rate limit per person on the routes.
 */
function limitedSnapshot(string $site = 'default'): Snapshot
{
    return Snapshot::create([
        'site' => $site,
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => ['entries_published' => ['count' => 42, 'previous' => null]],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
        'generated_by' => null,
    ]);
}

beforeEach(function () {
    Cache::flush();
    app()->instance(ImageRenderer::class, new FakeImageRenderer);
    app()->instance(VideoRenderer::class, new FakeVideoRenderer);
    $this->actingAs(User::make()->id('editor')->email('editor@example.com')->makeSuper());
    limitedSnapshot();
});

describe('one render at a time per site', function () {
    it('answers 429 with a Retry-After while another render holds the site, for images and the video alike', function () {
        $held = Cache::lock(RenderLock::key('default'), RenderLock::SECONDS);
        expect($held->get())->toBeTrue();

        try {
            $this->get(cp_route('wrapped.image.summary'))
                ->assertStatus(429)
                ->assertHeader('Retry-After', (string) RenderLock::RETRY_AFTER);
            $this->get(cp_route('wrapped.image', ['card' => 'entries_published']))->assertStatus(429);
            $this->get(cp_route('wrapped.video', ['cards' => ['entries_published']]))->assertStatus(429);
        } finally {
            $held->release();
        }
    });

    it('is per site: a render on another site does not block this one', function () {
        $held = Cache::lock(RenderLock::key('other'), RenderLock::SECONDS);
        expect($held->get())->toBeTrue();

        try {
            $this->get(cp_route('wrapped.image.summary'))->assertOk()->assertHeader('Content-Type', 'image/png');
        } finally {
            $held->release();
        }
    });

    it('releases the lock when the render finishes, and when it fails', function () {
        $this->get(cp_route('wrapped.image.summary'))->assertOk();

        $free = Cache::lock(RenderLock::key('default'), 1);
        expect($free->get())->toBeTrue();
        $free->release();

        // A card this Wrapped does not have: the renderer throws, the
        // controller says 404, and the site is not left locked.
        $this->get(cp_route('wrapped.image', ['card' => 'not_a_card']))->assertNotFound();

        $free = Cache::lock(RenderLock::key('default'), 1);
        expect($free->get())->toBeTrue();
        $free->release();
    });

    it('still renders when the cache store has no locks', function () {
        config(['cache.default' => 'null']);

        $this->get(cp_route('wrapped.image.summary'))->assertOk();
    });
});

describe('how often one person may ask', function () {
    it('answers 429 past the configured number a minute, and the screen itself is not counted', function () {
        config(['wrapped.exports.per_minute' => 2]);

        $this->get(cp_route('wrapped.image.summary'))->assertOk();
        $this->get(cp_route('wrapped.video', ['cards' => ['entries_published']]))->assertOk();
        $this->get(cp_route('wrapped.image', ['card' => 'entries_published']))
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // The limit is on the downloads, not on looking.
        $this->get(cp_route('wrapped.index'))->assertOk();
        $this->get(cp_route('wrapped.story'))->assertOk();
    });

    it('counts per person', function () {
        config(['wrapped.exports.per_minute' => 1]);

        $this->get(cp_route('wrapped.image.summary'))->assertOk();
        $this->get(cp_route('wrapped.image.summary'))->assertStatus(429);

        $this->actingAs(User::make()->id('someone-else')->email('else@example.com')->makeSuper());

        $this->get(cp_route('wrapped.image.summary'))->assertOk();
    });

    it('allows six a minute by default', function () {
        foreach (range(1, 6) as $i) {
            $this->get(cp_route('wrapped.image.summary'))->assertOk();
        }

        $this->get(cp_route('wrapped.image.summary'))->assertStatus(429);
    });
});
