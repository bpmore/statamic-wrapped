<?php

use Bpmore\Wrapped\Export\Theme;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Sharing\Share;
use Bpmore\Wrapped\Sharing\ShareLinks;
use Bpmore\Wrapped\Sharing\YouTube;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Stats\Voice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Role;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

/*
 * Sharing is switched ON for every test here. The off-by-default behaviour,
 * which is what a real install starts with, is proven in
 * ../Http/SharingOffTest.php.
 */

function sharer(array $permissions = [CardGate::VIEW, CardGate::SHARE]): void
{
    Role::make('sharer')->title('Sharer')->permissions(['access cp', ...$permissions])->save();

    test()->actingAs(User::make()->id('sharer')->email('sharer@example.com')->assignRole('sharer'));
}

function aWrapped(string $site = 'default', string $key = '2026'): Snapshot
{
    return Snapshot::create([
        'site' => $site,
        'period' => Period::Year,
        'period_key' => $key,
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => [
            'entries_published' => ['count' => 42, 'previous' => null],
            'people' => ['entries' => 42, 'people' => 3],
        ],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
        'generated_by' => null,
    ]);
}

function makeLink(array $data = []): TestResponse
{
    return test()->post(cp_route('wrapped.share.store'), $data + ['period' => '2026']);
}

beforeEach(function () {
    config(['wrapped.share.enabled' => true]);
    CarbonImmutable::setTestNow('2026-12-05 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

describe('making a link', function () {
    it('makes a public link and lands back on the same wrapped', function () {
        sharer();
        aWrapped();

        makeLink()->assertRedirect(cp_route('wrapped.index', ['period' => '2026']));

        $share = Share::sole();

        expect($share->token)->toMatch('/^[a-f0-9]{40}$/')
            ->and($share->site)->toBe('default')
            ->and($share->period_key)->toBe('2026')
            ->and($share->label)->toBe('2026')
            ->and($share->people)->toBeFalse()
            ->and($share->expires_at)->toBeNull()
            ->and($share->revoked_at)->toBeNull()
            ->and($share->created_by)->toBe('sharer')
            ->and($share->isLive())->toBeTrue();
    });

    it('freezes the cards as text, leaving the people cards out by default', function () {
        sharer([CardGate::VIEW, CardGate::VIEW_PEOPLE, CardGate::SHARE]);
        aWrapped();

        makeLink();

        expect(array_column(Share::sole()->cards, 'handle'))->toBe(['entries_published'])
            ->and(Share::sole()->cards[0]['body'])->toBe('We published 42 entries.');
    });

    it('speaks as the site by default: we, not you', function () {
        sharer();
        aWrapped();

        makeLink();

        expect(Share::sole()->voice)->toBe(Voice::We)
            ->and(Share::sole()->cards[0]['body'])->toBe('We published 42 entries.');
    });

    it('can be written as you, when asked', function () {
        sharer();
        aWrapped();

        makeLink(['voice' => 'you']);

        expect(Share::sole()->voice)->toBe(Voice::You)
            ->and(Share::sole()->cards[0]['body'])->toBe('You published 42 entries.');
    });

    it('has no music unless a song was pasted', function () {
        sharer();
        aWrapped();
        Http::fake();

        makeLink(['music' => '']);

        expect(Share::sole()->youtube_id)->toBeNull()
            ->and(Share::sole()->music())->toBeNull();

        Http::assertNothingSent();
    });

    it('keeps a pasted song by id, with the title and thumbnail YouTube gave', function () {
        sharer();
        aWrapped();
        Http::fake([YouTube::OEMBED.'*' => Http::response([
            'title' => 'Never Gonna Give You Up',
            'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ])]);

        makeLink(['music' => 'https://youtu.be/dQw4w9WgXcQ?si=abc'])->assertSessionHasNoErrors();

        $share = Share::sole();

        expect($share->youtube_id)->toBe('dQw4w9WgXcQ')
            ->and($share->youtube_title)->toBe('Never Gonna Give You Up')
            ->and($share->youtube_thumbnail)->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
            ->and($share->music()?->url())->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    });

    it('refuses a song link that is not YouTube, and makes no share', function () {
        sharer();
        aWrapped();
        Http::fake();

        makeLink(['music' => 'https://vimeo.com/123456'])->assertSessionHasErrors('music');

        expect(Share::count())->toBe(0);
        Http::assertNothingSent();
    });

    it('refuses a song YouTube does not know, and makes no share', function () {
        sharer();
        aWrapped();
        Http::fake([YouTube::OEMBED.'*' => Http::response('Not Found', 404)]);

        makeLink(['music' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])->assertSessionHasErrors('music');

        expect(Share::count())->toBe(0);
    });

    it('refuses a voice it does not have', function () {
        sharer();
        aWrapped();

        makeLink(['voice' => 'they'])->assertSessionHasErrors('voice');

        expect(Share::count())->toBe(0);
    });

    it('keeps saying you on a link made before there was a choice', function () {
        // A 1.2 row: written before the column existed, so no voice on it.
        DB::table('wrapped_shares')->insert([
            'token' => str_repeat('b', 40),
            'site' => 'default',
            'period_key' => '2026',
            'label' => '2026',
            'cards' => json_encode([['handle' => 'entries_published', 'heading' => 'Entries published', 'body' => 'You published 42 entries.']]),
            'people' => false,
            'created_at' => CarbonImmutable::now(),
        ]);

        $share = Share::sole();

        expect($share->voice)->toBe(Voice::You);

        $this->get($share->url())
            ->assertSee('You published 42 entries.')
            ->assertSee('That was your year.');
    });

    it('includes the people cards when asked, by someone who may see them', function () {
        sharer([CardGate::VIEW, CardGate::VIEW_PEOPLE, CardGate::SHARE]);
        aWrapped();

        makeLink(['people' => true]);

        expect(array_column(Share::sole()->cards, 'handle'))->toBe(['entries_published', 'people'])
            ->and(Share::sole()->people)->toBeTrue();
    });

    it('never publishes people cards the maker may not see, even when asked', function () {
        sharer([CardGate::VIEW, CardGate::SHARE]);
        aWrapped();

        makeLink(['people' => true]);

        expect(array_column(Share::sole()->cards, 'handle'))->toBe(['entries_published'])
            ->and(Share::sole()->people)->toBeFalse();
    });

    it('keeps saying what it said when the snapshot is regenerated', function () {
        sharer();
        $snapshot = aWrapped();

        makeLink();

        $snapshot->update(['stats' => ['entries_published' => ['count' => 99, 'previous' => null]]]);

        $this->get(Share::sole()->url())
            ->assertOk()
            ->assertSee('We published 42 entries.')
            ->assertDontSee('99');
    });

    it('can be given an expiry', function () {
        sharer();
        aWrapped();

        makeLink(['days' => 30]);

        expect(Share::sole()->expires_at->toDateString())->toBe('2027-01-04');
    });

    it('refuses an expiry that is not a sensible number of days', function (mixed $days) {
        sharer();
        aWrapped();

        makeLink(['days' => $days])->assertSessionHasErrors('days');

        expect(Share::count())->toBe(0);
    })->with([0, -1, 401, 'soon']);

    it('is a 404 for a period the site has not built', function () {
        sharer();
        aWrapped();

        makeLink(['period' => '2031'])->assertNotFound();

        expect(Share::count())->toBe(0);
    });

    it('needs the share permission, not just view', function () {
        sharer([CardGate::VIEW]);
        aWrapped();

        // Statamic turns a denied CP request into a redirect home.
        makeLink()->assertRedirect(cp_route('index'));

        expect(Share::count())->toBe(0);
    });

    it('is refused to a visitor', function () {
        aWrapped();

        makeLink()->assertRedirect();

        expect(Share::count())->toBe(0);
    });
});

describe('the public page', function () {
    it('serves the frozen wrapped to anyone with the link, no login', function () {
        sharer();
        aWrapped();
        makeLink();
        auth()->logout();

        $this->get(Share::sole()->url())
            ->assertOk()
            ->assertSee('2026 Wrapped')
            ->assertSee('Entries published')
            ->assertSee('We published 42 entries.')
            ->assertSee('noindex', false);
    });

    it('closes with the line for its period', function () {
        sharer();
        aWrapped(key: '2026-08');
        Snapshot::where('period_key', '2026-08')->update(['period' => Period::Month]);

        makeLink(['period' => '2026-08']);

        $this->get(Share::sole()->url())
            ->assertSee('August 2026 Wrapped')
            ->assertSee('That was our month.');
    });

    it('closes in the voice it was written in', function () {
        sharer();
        aWrapped(key: '2026-08');
        Snapshot::where('period_key', '2026-08')->update(['period' => Period::Month]);

        makeLink(['period' => '2026-08', 'voice' => 'you']);

        $this->get(Share::sole()->url())
            ->assertSee('That was your month.')
            ->assertDontSee('That was our month.');
    });

    it('is the story: one frame per card, tap-through, with nothing of the control panel', function () {
        sharer();
        aWrapped();
        makeLink();
        auth()->logout();

        $html = $this->get(Share::sole()->url())->assertOk()->getContent();

        expect($html)
            // Every frame is in the HTML, so it reads as a plain page without script.
            ->toContain('2026 Wrapped')
            ->toContain('We published 42 entries.')
            ->toContain('That was our year.')
            ->toContain('role="progressbar"')
            ->toContain('aria-valuemax="3"')
            ->toContain('aria-label="Previous"')
            ->toContain('aria-label="Next"')
            // A share button, and a status line a screen reader hears when it copies.
            ->toContain('data-share')
            ->toContain('role="status" aria-live="polite"')
            // The footer is decoration for sighted readers; the frames already say it.
            ->toContain('class="story-footer" aria-hidden="true"')
            // A posted link gets a title and a first line.
            ->toContain('<meta property="og:title" content="2026 Wrapped · Laravel">')
            ->toContain('<meta property="og:description" content="We published 42 entries.">')
            // Nothing of the control panel: no links into it, no assets from it.
            ->not->toContain('/cp/')
            ->not->toContain('<script src')
            ->not->toContain('csrf');
    });

    it('wears the site\'s theme', function () {
        config(['wrapped.theme.background' => '#fef3c7']);
        app()->instance(Theme::class, Theme::fromConfig());
        sharer();
        aWrapped();
        makeLink();

        $this->get(Share::sole()->url())
            ->assertSee('--story-bg: #fef3c7', false)
            ->assertSee('--story-text: #16161d', false)
            ->assertSee('<meta name="theme-color" content="#fef3c7">', false);
    });

    it('is a 404 for a token that does not exist', function () {
        $this->get(route('wrapped.share.show', ['token' => str_repeat('a', 40)]))->assertNotFound();
    });

    it('is a 404 for a token of the wrong shape, before touching the database', function () {
        $this->get('/wrapped/not-a-token')->assertNotFound();
    });

    it('stops serving once revoked', function () {
        sharer();
        aWrapped();
        makeLink();

        $share = Share::sole();
        $share->revoke();

        $this->get($share->url())->assertNotFound();
    });

    it('stops serving once expired', function () {
        sharer();
        aWrapped();
        makeLink(['days' => 7]);

        $share = Share::sole();

        CarbonImmutable::setTestNow('2026-12-12 08:59:59');
        $this->get($share->url())->assertOk();

        CarbonImmutable::setTestNow('2026-12-12 09:00:01');
        $this->get($share->url())->assertNotFound();
    });

    it('stops serving every link when sharing is switched off', function () {
        sharer();
        aWrapped();
        makeLink();

        $url = Share::sole()->url();
        config(['wrapped.share.enabled' => false]);

        $this->get($url)->assertNotFound();
    });
});

describe('revoking', function () {
    it('revokes a link and lands back on the same wrapped', function () {
        sharer();
        aWrapped();
        makeLink();

        $share = Share::sole();

        $this->delete(cp_route('wrapped.share.destroy', $share))
            ->assertRedirect(cp_route('wrapped.index', ['period' => '2026']));

        expect($share->fresh()->revoked_at)->not->toBeNull()
            ->and($share->fresh()->isLive())->toBeFalse();
    });

    it('cannot revoke another site\'s link by id', function () {
        sharer();
        aWrapped();
        makeLink();

        $share = Share::sole();
        $share->update(['site' => 'french']);

        $this->delete(cp_route('wrapped.share.destroy', $share))->assertNotFound();

        expect($share->fresh()->isLive())->toBeTrue();
    });

    it('needs the share permission', function () {
        sharer();
        aWrapped();
        makeLink();
        $share = Share::sole();

        sharer([CardGate::VIEW]);

        $this->delete(cp_route('wrapped.share.destroy', $share))->assertRedirect(cp_route('index'));

        expect($share->fresh()->isLive())->toBeTrue();
    });
});

describe('on the wrapped screen', function () {
    it('lists the live links for this wrapped, and only this one', function () {
        sharer([CardGate::VIEW, CardGate::VIEW_PEOPLE, CardGate::SHARE]);
        aWrapped();
        aWrapped(key: '2025');
        makeLink(['days' => 30]);
        makeLink(['period' => '2025']);
        Share::where('period_key', '2025')->sole()->revoke();
        makeLink(['period' => '2025']);

        $this->get(cp_route('wrapped.index', ['period' => '2026']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('snapshot.share.createUrl', cp_route('wrapped.share.store'))
                ->where('snapshot.share.canPeople', true)
                ->where('snapshot.share.maxDays', ShareLinks::MAX_DAYS)
                ->has('snapshot.share.links', 1)
                ->where('snapshot.share.links.0.url', Share::where('period_key', '2026')->sole()->url())
                ->where('snapshot.share.links.0.expiresAt', '2027-01-04T09:00:00+00:00')
                ->where('snapshot.share.links.0.people', false)
                ->where('snapshot.share.links.0.voice', 'we')
                ->where('snapshot.share.links.0.music', null)
                ->where('snapshot.share.links.0.revokeUrl', cp_route('wrapped.share.destroy', Share::where('period_key', '2026')->sole())));

        $this->get(cp_route('wrapped.index', ['period' => '2025']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('snapshot.share.links', 1));
    });

    it('tells the screen which song a link plays', function () {
        sharer();
        aWrapped();
        Http::fake([YouTube::OEMBED.'*' => Http::response(['title' => 'Never Gonna Give You Up', 'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg'])]);
        makeLink(['music' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->get(cp_route('wrapped.index', ['period' => '2026']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('snapshot.share.links.0.music', [
                    'id' => 'dQw4w9WgXcQ',
                    'title' => 'Never Gonna Give You Up',
                    'thumbnail' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                ]));
    });

    it('does not offer sharing to someone without the permission', function () {
        sharer([CardGate::VIEW]);
        aWrapped();

        $this->get(cp_route('wrapped.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('snapshot.share', null));
    });
});

describe('music on the public page', function () {
    function withASong(): void
    {
        sharer();
        aWrapped();
        Http::fake([YouTube::OEMBED.'*' => Http::response(['title' => 'Never Gonna Give You Up', 'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg'])]);
        makeLink(['music' => 'https://youtu.be/dQw4w9WgXcQ']);
        auth()->logout();
    }

    it('offers the song, named as YouTube named it, and plays nothing until asked', function () {
        withASong();

        $html = $this->get(Share::sole()->url())->assertOk()->getContent();

        expect($html)
            // The tile, empty, with what the script needs to fill it.
            ->toContain('data-music-id="dQw4w9WgXcQ"')
            ->toContain('<div data-music-player></div>')
            ->toContain('<span class="story-music__title">Never Gonna Give You Up</span>')
            // YouTube's terms, linked, as their policies ask of a page with their player.
            ->toContain('href="https://www.youtube.com/t/terms"')
            // Two ways in: on the intro, and in the controls.
            ->toContain('data-label="Play music"')
            ->toContain('data-label="Music"')
            // Privacy-enhanced domain, looping, and only ever built by the reader's press.
            ->toContain('https://www.youtube-nocookie.com/embed/')
            ->toContain('loop=1&playlist=')
            // No player in the HTML itself: nothing loads from YouTube until asked.
            ->not->toContain('<iframe')
            ->not->toContain('<script src');
    });

    it('has no tile, no buttons and no mention of YouTube when the link has no song', function () {
        sharer();
        aWrapped();
        makeLink();
        auth()->logout();

        $html = $this->get(Share::sole()->url())->assertOk()->getContent();

        expect($html)
            ->not->toContain('data-music')
            ->not->toContain('youtube')
            ->not->toContain('YouTube');
    });

    it('escapes a song title that tries to be markup', function () {
        sharer();
        aWrapped();
        Http::fake([YouTube::OEMBED.'*' => Http::response(['title' => '<script>alert(1)</script> & co'])]);
        makeLink(['music' => 'https://youtu.be/dQw4w9WgXcQ']);

        $html = $this->get(Share::sole()->url())->assertOk()->getContent();

        expect($html)
            ->not->toContain('<script>alert(1)</script>')
            ->toContain('&lt;script&gt;alert(1)&lt;/script&gt; &amp; co');
    });
});
