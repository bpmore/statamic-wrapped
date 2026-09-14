<?php

use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\Export\Soundtracks;
use Bpmore\Wrapped\Export\VideoRenderer;
use Bpmore\Wrapped\Export\VideoSpec;
use Bpmore\Wrapped\Export\WrappedVideo;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Bpmore\Wrapped\Tests\Fixtures\FakeVideoRenderer;
use Carbon\CarbonImmutable;
use Statamic\Facades\Role;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

/**
 * Twelve cards, two of them about people, so the default has to choose.
 */
function aFullWrapped(): Snapshot
{
    return new Snapshot([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => [
            'entries_published' => ['count' => 47, 'previous' => 21],
            'total_words' => ['words' => 18722, 'entries' => 47],
            'assets_uploaded' => ['count' => 12, 'bytes' => 7500000, 'weighed' => 12],
            'busiest_month' => ['year' => 2026, 'month' => 3, 'count' => 12],
            'busiest_week' => ['starts_on' => '2026-03-02', 'week' => 10, 'count' => 6],
            'busiest_time' => ['day' => 2, 'day_count' => 20, 'hour' => 14, 'hour_count' => 18, 'from' => 40],
            'longest_streak' => ['days' => 5, 'from' => '2026-03-02', 'to' => '2026-03-06'],
            'longest_entry' => ['id' => 'a', 'title' => 'The Long Read', 'collection' => 'blog', 'words' => 2404],
            'most_revised' => ['id' => 'b', 'title' => 'Pricing', 'collection' => 'pages', 'edits' => 47],
            'top_taxonomy_term' => ['taxonomy' => 'topics', 'term' => 'design', 'count' => 46],
            'people' => ['people' => 3, 'entries' => 47, 'unattributed' => 0],
            'top_contributor' => ['author' => 'ada', 'count' => 20],
        ],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
    ]);
}

function video(): WrappedVideo
{
    return app(WrappedVideo::class);
}

beforeEach(function () {
    app()->instance(ImageRenderer::class, new FakeImageRenderer);
    app()->instance(VideoRenderer::class, $this->videoRenderer = new FakeVideoRenderer);

    $this->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper());
});

describe('the default selection', function () {
    it('ticks up to eight cards, in screen order, and never a people card', function () {
        $selected = video()->defaultSelection(aFullWrapped());

        expect($selected)->toHaveCount(8)
            ->and($selected[0])->toBe('entries_published')
            ->and($selected)->not->toContain('people')
            ->and($selected)->not->toContain('top_contributor');
    });

    it('offers every visible card as a choice, marking which are about people', function () {
        $choices = collect(video()->choices(aFullWrapped()));

        expect($choices)->toHaveCount(12)
            ->and($choices->firstWhere('handle', 'people')['people'])->toBeTrue()
            ->and($choices->firstWhere('handle', 'people')['selected'])->toBeFalse()
            ->and($choices->firstWhere('handle', 'entries_published')['people'])->toBeFalse()
            ->and($choices->firstWhere('handle', 'entries_published')['selected'])->toBeTrue();
    });

    it('ticks everything on a small wrapped', function () {
        $snapshot = aFullWrapped();
        $snapshot->stats = array_slice($snapshot->stats, 0, 3, true);

        expect(video()->defaultSelection($snapshot))->toHaveCount(3);
    });

    it('says how long a choice would run, from the actual words on the cards', function () {
        $snapshot = aFullWrapped();

        $one = video()->duration($snapshot, ['assets_uploaded']);
        $wordy = video()->duration($snapshot, ['entries_published']);
        $eight = video()->duration($snapshot, video()->defaultSelection($snapshot));

        // Intro + one card + outro; a wordier card runs longer; more cards run longer still.
        expect($one)->toBeGreaterThan(10.0)
            ->and($wordy)->toBeGreaterThan($one)
            ->and($eight)->toBeGreaterThan($wordy)
            ->and(video()->duration($snapshot, []))->toBe((new VideoSpec)->duration([4.0, 4.0]));
    });

    it('agrees with itself: the readout matches what the frames would be timed at', function () {
        $snapshot = aFullWrapped();
        $handles = ['busiest_time', 'entries_published'];

        video()->render($snapshot, $handles);

        expect(video()->duration($snapshot, $handles))
            ->toBe((new VideoSpec)->duration($this->videoRenderer->frames));
    });
});

describe('rendering', function () {
    it('wraps the chosen cards in an intro and an outro, in the order chosen', function () {
        $mp4 = video()->render(aFullWrapped(), ['busiest_time', 'entries_published']);

        expect($mp4)->toBe('MP4:4frames:soft-landing.m4a')
            ->and($this->videoRenderer->frames)->toHaveCount(4);
    });

    it('pins the footer over every frame rather than drawing it on each', function () {
        video()->render(aFullWrapped(), ['busiest_time']);

        expect($this->videoRenderer->overlay)->toBe('PNG:1080x1920:transparent');

        // Exactly one transparent render: the footer. The frames are opaque.
        $renders = collect(app(ImageRenderer::class)->renders);

        expect($renders->where('transparent', true))->toHaveCount(1)
            ->and($renders->where('transparent', false))->toHaveCount(3);
    });

    it('uses the default soundtrack unless told otherwise', function () {
        video()->render(aFullWrapped(), ['entries_published']);

        expect($this->videoRenderer->audio)->toEndWith('soft-landing.m4a');

        video()->render(aFullWrapped(), ['entries_published'], 'seoul-rooftop');

        expect($this->videoRenderer->audio)->toEndWith('seoul-rooftop.m4a');
    });

    it('goes silent when there are no soundtracks at all', function () {
        config(['wrapped.video.bundled' => false]);
        app()->forgetInstance(Soundtracks::class);

        expect(video()->render(aFullWrapped(), ['entries_published']))->toBe('MP4:3frames:silent');
    });

    it('ignores a card asked for twice', function () {
        video()->render(aFullWrapped(), ['entries_published', 'entries_published']);

        expect($this->videoRenderer->frames)->toHaveCount(3);
    });

    it('names the file so it can be found again', function () {
        expect(video()->filename(aFullWrapped()))->toBe('wrapped-2026-default.mp4');
    });
});

describe('refusing what should not go in', function () {
    it('needs at least one card', function () {
        expect(fn () => video()->render(aFullWrapped(), []))
            ->toThrow(RuntimeException::class, 'at least one card');
    });

    it('refuses a card this wrapped does not have', function () {
        expect(fn () => video()->render(aFullWrapped(), ['nonsense']))
            ->toThrow(RuntimeException::class, 'no [nonsense] card');
    });

    it('refuses a soundtrack it does not have', function () {
        expect(fn () => video()->render(aFullWrapped(), ['entries_published'], 'nope'))
            ->toThrow(RuntimeException::class, 'no soundtrack called [nope]');
    });

    it('will not let a people card in through the url for a viewer who may not see it', function () {
        $roles = sys_get_temp_dir().'/wrapped-roles-'.bin2hex(random_bytes(4));
        mkdir($roles, 0777, true);
        config(['statamic.users.repositories.file.paths.roles' => $roles.'/roles.yaml']);
        Stache::clear();

        Role::make('viewer')->title('Viewer')->permissions(['access cp', CardGate::VIEW])->save();
        $this->actingAs(User::make()->id('viewer')->email('viewer@example.com')->assignRole('viewer'));

        try {
            expect(collect(video()->choices(aFullWrapped()))->pluck('handle')->all())->not->toContain('people');

            expect(fn () => video()->render(aFullWrapped(), ['people']))
                ->toThrow(RuntimeException::class, 'no [people] card');
        } finally {
            exec('rm -rf '.escapeshellarg($roles));
        }
    });
});
