<?php

use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\Export\VideoRenderer;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Bpmore\Wrapped\Tests\Fixtures\FakeVideoRenderer;
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
            ->where('snapshot.history.confidence', 'high')
            ->where('snapshot.history.source', 'logbook')
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
            ->where('snapshot.history.confidence', 'low')
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

it('never shows a wrapped without saying what it was built from', function () {
    storeSnapshot(['entries_published' => ['count' => 42, 'previous' => null]]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('snapshot.history.notice', 'Built from Statamic Logbook.')
            ->where('snapshot.history.limited', false)
            ->where('snapshot.history.sourceName', 'Statamic Logbook')
        );
});

it('warns on the screen when the history was too thin for some cards', function () {
    Snapshot::create([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'mtime',
        'confidence' => Confidence::Low,
        'stats' => [],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
        'generated_by' => null,
    ]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('snapshot.history.limited', true)
            ->where('snapshot.history.notice', 'There is not enough history here to say anything yet. This site has only file modification times to go on, and most deploys rewrite those.')
            ->where('snapshot.history.suggestion', 'Statamic Logbook records who changed what and when, and would give a much fuller picture next time.')
        );
});

it('offers a download for each card and for the whole thing', function () {
    app()->instance(ImageRenderer::class, new FakeImageRenderer);
    storeSnapshot(['entries_published' => ['count' => 42, 'previous' => null]]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('snapshot.canExport', true));

    $this->get(cp_route('wrapped.image', 'entries_published'))
        ->assertOk()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('content-disposition', 'attachment; filename="wrapped-2026-default-entries_published.png"');

    $this->get(cp_route('wrapped.image.summary'))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename="wrapped-2026-default.png"');
});

it('hides the download when there is no browser to render with', function () {
    app()->instance(
        ImageRenderer::class,
        new FakeImageRenderer(available: false),
    );
    storeSnapshot(['entries_published' => ['count' => 42, 'previous' => null]]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('snapshot.canExport', false));
});

it('does not export a card this wrapped never had', function () {
    app()->instance(ImageRenderer::class, new FakeImageRenderer);
    storeSnapshot(['entries_published' => ['count' => 42, 'previous' => null]]);

    $this->get(cp_route('wrapped.image', 'nonsense'))->assertNotFound();
});

it('does not export before anything has been generated', function () {
    app()->instance(ImageRenderer::class, new FakeImageRenderer);

    $this->get(cp_route('wrapped.image.summary'))->assertNotFound();
});

it('hands over suggested alt text with every image', function () {
    app()->instance(ImageRenderer::class, new FakeImageRenderer);
    storeSnapshot(['entries_published' => ['count' => 42, 'previous' => null]]);

    $this->get(cp_route('wrapped.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('snapshot.cards.0.alt', 'Entries published: You published 42 entries. From the 2026 Wrapped for default.')
            ->where('snapshot.summaryAlt', 'The 2026 Wrapped for default. Entries published: You published 42 entries.')
        );
});

describe('the video maker', function () {
    beforeEach(function () {
        app()->instance(ImageRenderer::class, new FakeImageRenderer);
        app()->instance(VideoRenderer::class, $this->videoRenderer = new FakeVideoRenderer);

        storeSnapshot([
            'entries_published' => ['count' => 42, 'previous' => null],
            'busiest_time' => ['day' => 2, 'day_count' => 9, 'hour' => 14, 'hour_count' => 7, 'from' => 20],
            'people' => ['people' => 3, 'entries' => 42, 'unattributed' => 0],
        ]);
    });

    it('hands the screen everything it needs to let the editor choose', function () {
        $this->get(cp_route('wrapped.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('snapshot.video.available', true)
                ->has('snapshot.video.choices', 3)
                ->where('snapshot.video.choices.0.handle', 'entries_published')
                ->where('snapshot.video.choices.0.selected', true)
                ->where('snapshot.video.choices.0.people', false)
                // 4.0 in PHP, 4 once it has been through JSON.
                ->where('snapshot.video.choices.0.seconds', 4)
                ->where('snapshot.video.choices.2.handle', 'people')
                ->where('snapshot.video.choices.2.people', true)
                ->where('snapshot.video.choices.2.selected', false)
                ->where('snapshot.video.titleSeconds', 2.5)
                ->where('snapshot.video.crossfade', 0.8)
                ->where('snapshot.video.defaultTrack', 'soft-landing')
                ->where('snapshot.video.filename', 'wrapped-2026-default.mp4')
                ->where('snapshot.video.descriptionLead', 'A short video of the 2026 Wrapped for default, one fact per screen, with music.')
                ->has('snapshot.video.tracks', 5)
                ->where('snapshot.video.tracks.0.handle', 'soft-landing')
                ->where('snapshot.video.tracks.0.mimeType', 'audio/mp4')
                ->where('snapshot.video.tracks.0.previewUrl', cp_route('wrapped.soundtrack', 'soft-landing'))
                ->missing('snapshot.video.tracks.0.path'));
    });

    it('says the video is unavailable without ffmpeg', function () {
        app()->instance(
            VideoRenderer::class,
            new FakeVideoRenderer(available: false),
        );

        $this->get(cp_route('wrapped.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('snapshot.video.available', false));
    });

    it('downloads a video of the chosen cards with the chosen track', function () {
        $this->get(cp_route('wrapped.video', ['cards' => ['busiest_time', 'entries_published'], 'track' => 'seoul-rooftop']))
            ->assertOk()
            ->assertHeader('content-type', 'video/mp4')
            ->assertHeader('content-disposition', 'attachment; filename="wrapped-2026-default.mp4"');

        expect($this->videoRenderer->frames)->toHaveCount(4)
            ->and($this->videoRenderer->audio)->toEndWith('seoul-rooftop.m4a');
    });

    it('falls back to the default track', function () {
        $this->get(cp_route('wrapped.video', ['cards' => ['entries_published']]))->assertOk();

        expect($this->videoRenderer->audio)->toEndWith('soft-landing.m4a');
    });

    it('refuses a video with no cards', function () {
        $this->get(cp_route('wrapped.video'))->assertNotFound();
    });

    it('refuses a card that is not on the wrapped', function () {
        $this->get(cp_route('wrapped.video', ['cards' => ['nonsense']]))->assertNotFound();
    });

    it('refuses a track it does not have', function () {
        $this->get(cp_route('wrapped.video', ['cards' => ['entries_published'], 'track' => 'nope']))->assertNotFound();
    });

    it('streams a soundtrack for the preview button', function () {
        $this->get(cp_route('wrapped.soundtrack', 'open-road'))
            ->assertOk()
            ->assertHeader('content-type', 'audio/mp4');
    });

    it('does not stream a soundtrack it does not have', function () {
        $this->get(cp_route('wrapped.soundtrack', 'nope'))->assertNotFound();
    });
});
