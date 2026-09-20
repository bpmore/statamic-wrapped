<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Export\AltText;
use Bpmore\Wrapped\Export\CardImages;
use Bpmore\Wrapped\Export\Soundtrack;
use Bpmore\Wrapped\Export\SoundtrackResponse;
use Bpmore\Wrapped\Export\Soundtracks;
use Bpmore\Wrapped\Export\Theme;
use Bpmore\Wrapped\Export\VideoSpec;
use Bpmore\Wrapped\Export\WrappedVideo;
use Bpmore\Wrapped\Sharing\Share;
use Bpmore\Wrapped\Sharing\ShareLinks;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Stats\CardPresenter;
use Bpmore\Wrapped\Stats\ConfidenceNotice;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Wrapped screen.
 *
 * This is the canonical version of a Wrapped: real text, real headings, and
 * everything a shareable image will later say. The images are a convenience —
 * an accessibility addon shipping image-only output is the screenshot nobody
 * wants — see SPEC.md §5.
 */
class WrappedController extends CpController
{
    public function index(Request $request, CardPresenter $presenter, ConfidenceNotice $notice, CardImages $images, AltText $alt, WrappedVideo $video, Soundtracks $soundtracks, ShareLinks $links, CardGate $gate): Response
    {
        $snapshot = $this->chosen($request);

        return Inertia::render('wrapped::Wrapped', [
            // The site as a person knows it; the screen only ever shows it.
            'site' => Site::selected()->name(),
            // Every snapshot this site has, for the picker. A site building
            // monthly and yearly has a dozen-plus, and the newest built is
            // not always the one someone came for.
            'periods' => $snapshot === null ? [] : $this->periods($snapshot),
            // The Build form, for someone allowed to press it; null keeps the
            // form off the screen for everyone else.
            'generate' => $gate->canGenerate() ? $this->generating() : null,
            'snapshot' => $snapshot === null ? null : [
                'period' => $snapshot->period->value,
                'periodKey' => $snapshot->period_key,
                // "Since June 2026" for a site younger than the period; the
                // bare period otherwise. SPEC.md §1's first-year framing.
                'label' => $snapshot->label(),
                'generatedAt' => $snapshot->generated_at->toIso8601String(),
                'cards' => $this->cards($presenter, $alt, $snapshot),
                // A Wrapped never appears without saying what it was built
                // from — SPEC.md §1.
                'history' => $notice->for($snapshot),
                // No browser, no download button. The screen is the real
                // version and works either way.
                'canExport' => $images->isAvailable(),
                'summaryAlt' => $alt->forSummary($snapshot),
                'summaryImageUrl' => $this->route('wrapped.image.summary', $snapshot),
                'video' => $this->videoMaker($video, $soundtracks, $snapshot),
                'videoUrl' => $this->route('wrapped.video', $snapshot),
                'storyUrl' => $this->route('wrapped.story', $snapshot),
                // Null unless sharing is on and this user may do it; the
                // panel does not appear at all otherwise.
                'share' => $links->canShare() ? $this->sharing($links, $gate, $snapshot, $soundtracks) : null,
            ],
        ]);
    }

    /**
     * What the Build form needs: where to post, and today's year, quarter and
     * month so its pickers open on the period most likely wanted.
     *
     * @return array{url: string, year: int, quarter: int, month: int}
     */
    protected function generating(): array
    {
        $now = CarbonImmutable::now();

        return [
            'url' => cp_route('wrapped.generate'),
            'year' => (int) $now->year,
            'quarter' => (int) $now->quarter,
            'month' => (int) $now->month,
        ];
    }

    /**
     * The live public links for this snapshot, and what is needed to make
     * another.
     *
     * @return array<string, mixed>
     */
    protected function sharing(ShareLinks $links, CardGate $gate, Snapshot $snapshot, Soundtracks $soundtracks): array
    {
        return [
            'createUrl' => cp_route('wrapped.share.store'),
            'canPeople' => $gate->canViewPeople(),
            'maxDays' => ShareLinks::MAX_DAYS,
            'tracks' => $soundtracks->all()->map(fn (Soundtrack $track) => $track->toArray() + [
                'previewUrl' => cp_route('wrapped.soundtrack', ['handle' => $track->handle]),
            ])->values()->all(),
            'links' => $links->liveFor($snapshot)->map(fn (Share $share) => [
                'id' => $share->id,
                'url' => $share->url(),
                'createdAt' => $share->created_at->toIso8601String(),
                'expiresAt' => $share->expires_at?->toIso8601String(),
                'people' => $share->people,
                'voice' => $share->voice->value,
                'music' => $share->music()?->toArray(),
                'soundtrack' => ($track = $share->soundtrack($soundtracks)) === null ? null : ['handle' => $track->handle, 'name' => $track->name],
                'revokeUrl' => cp_route('wrapped.share.destroy', $share),
            ])->values()->all(),
        ];
    }

    /**
     * The site's snapshots as picker entries: years, then quarters, then
     * months, each newest first. The year is the headline and stays at the
     * top however many months pile up under it: 2026, Q3 2026, Q2 2026,
     * August 2026, July 2026, ...
     *
     * @return list<array{key: string, label: string, url: string, current: bool}>
     */
    protected function periods(Snapshot $current): array
    {
        return Snapshot::query()
            ->where('site', $current->site)
            ->get()
            ->sortBy([
                fn (Snapshot $a, Snapshot $b) => $a->period->parts() <=> $b->period->parts(),
                fn (Snapshot $a, Snapshot $b) => $b->startsOn() <=> $a->startsOn(),
            ])
            ->values()
            ->map(fn (Snapshot $snapshot) => [
                'key' => $snapshot->period_key,
                'label' => $snapshot->label(),
                'url' => $this->route('wrapped.index', $snapshot),
                'current' => $snapshot->is($current),
            ])
            ->all();
    }

    /**
     * Each card with the alt text that should go out alongside its image.
     *
     * @return list<array{handle: string, heading: string, body: string, alt: string, imageUrl: string}>
     */
    protected function cards(CardPresenter $presenter, AltText $alt, Snapshot $snapshot): array
    {
        return array_map(
            fn (array $card) => $card + [
                'alt' => $alt->forCard($snapshot, $card),
                'imageUrl' => $this->route('wrapped.image', $snapshot, ['card' => $card['handle']]),
            ],
            $presenter->present($snapshot->stats),
        );
    }

    /**
     * The Wrapped as a story: one fact per screen, advanced by the reader.
     *
     * This is the accessible form of the video, and arguably the canonical
     * one. Nothing moves until the reader says so, which is WCAG 2.2.1 and
     * 2.2.2 satisfied by design rather than by a timing formula; it works from
     * a keyboard; and every frame is real text a screen reader can read. The
     * MP4 is the shareable export of this.
     */
    public function story(Request $request, CardPresenter $presenter, Soundtracks $soundtracks, Theme $theme): Response
    {
        $snapshot = $this->chosen($request);

        if ($snapshot === null) {
            throw new NotFoundHttpException('There is no Wrapped to tell yet.');
        }

        $frames = [['kind' => 'intro', 'title' => __('wrapped::messages.story.intro', ['period' => $snapshot->label()]), 'subtitle' => $snapshot->siteName()]];

        foreach ($presenter->present($snapshot->stats) as $card) {
            $frames[] = ['kind' => 'card', 'handle' => $card['handle'], 'heading' => $card['heading'], 'body' => $card['body']];
        }

        $frames[] = ['kind' => 'outro', 'title' => Period::outro($snapshot->period_key)];

        return Inertia::render('wrapped::Story', [
            'theme' => $theme->toArray(),
            'site' => $snapshot->siteName(),
            'label' => $snapshot->label(),
            'backUrl' => $this->route('wrapped.index', $snapshot),
            'frames' => $frames,
            'tracks' => $soundtracks->all()->values()->map(fn ($track) => $track->toArray() + [
                'mimeType' => $track->mimeType(),
                'url' => cp_route('wrapped.soundtrack', $track->handle),
            ])->all(),
            'defaultTrack' => $soundtracks->default()?->handle,
        ]);
    }

    /**
     * Everything the video maker needs to let the editor choose, and to say
     * how long the result will run before they commit to it.
     *
     * The per-card seconds and the two constants are sent so the running time
     * can be added up on the client as boxes are ticked. The server remains
     * the authority when the file is actually made.
     *
     * @return array<string, mixed>
     */
    protected function videoMaker(WrappedVideo $video, Soundtracks $soundtracks, Snapshot $snapshot): array
    {
        $spec = new VideoSpec;

        return [
            'available' => $video->isAvailable(),
            'choices' => array_map(
                fn (array $choice) => $choice + ['seconds' => $spec->secondsFor($choice['body'])],
                $video->choices($snapshot),
            ),
            'titleSeconds' => $spec->titleSeconds,
            'crossfade' => $spec->crossfade,
            'tracks' => $soundtracks->all()->values()->map(fn ($track) => $track->toArray() + [
                'mimeType' => $track->mimeType(),
                'previewUrl' => cp_route('wrapped.soundtrack', $track->handle),
            ])->all(),
            'defaultTrack' => $soundtracks->default()?->handle,
            // The opening line of the video's description; the client appends
            // the chosen cards' own sentences to it.
            'descriptionLead' => __('wrapped::messages.alt.video', [
                'period' => Period::label($snapshot->period_key),
                'site' => $snapshot->siteName(),
            ]),
            'filename' => $video->filename($snapshot),
        ];
    }

    /**
     * The video, downloaded rather than hosted, with the editor's choice of
     * cards and track carried in the query. Same rules as the images: no
     * public URL, ever.
     */
    public function video(Request $request, WrappedVideo $video): HttpResponse
    {
        $snapshot = $this->chosen($request);

        if ($snapshot === null) {
            throw new NotFoundHttpException('There is no Wrapped to make a video from yet.');
        }

        $cards = array_values(array_filter(
            (array) $request->query('cards', []),
            fn ($card) => is_string($card) && $card !== '',
        ));

        $track = $request->query('track');
        $track = is_string($track) && $track !== '' ? $track : null;

        try {
            $mp4 = $video->render($snapshot, $cards, $track);
        } catch (RuntimeException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        return response($mp4, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'attachment; filename="'.$video->filename($snapshot).'"',
        ]);
    }

    /**
     * A soundtrack, for the preview button. Streamed from wherever the file
     * lives — the addon's own directory, or the site's — never copied into
     * public/. Sits behind the same permission as the screen.
     */
    public function soundtrack(Soundtracks $soundtracks, string $handle): SymfonyResponse
    {
        $track = $soundtracks->find($handle);

        if ($track === null) {
            throw new NotFoundHttpException("There is no soundtrack called [{$handle}].");
        }

        return SoundtrackResponse::for($track);
    }

    /**
     * A card as a PNG, downloaded rather than hosted.
     *
     * There is no public URL for a Wrapped and there will not be one: a link
     * leaking a team's internal publishing stats is a support problem and a
     * privacy problem for a free addon — SPEC.md §5.
     */
    public function image(Request $request, CardImages $images, ?string $card = null): HttpResponse
    {
        $snapshot = $this->chosen($request);

        if ($snapshot === null) {
            throw new NotFoundHttpException('There is no Wrapped to export yet.');
        }

        try {
            $png = $card === null
                ? $images->summary($snapshot)
                : $images->card($snapshot, $card);
        } catch (RuntimeException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$images->filename($snapshot, $card).'"',
        ]);
    }

    /**
     * A route for this snapshot. The period rides in the query on every URL
     * the screen hands out, so the story, images and video all follow the
     * picker rather than silently reverting to the newest build.
     *
     * @param  array<string, string>  $parameters
     */
    protected function route(string $name, Snapshot $snapshot, array $parameters = []): string
    {
        return cp_route($name, $parameters + ['period' => $snapshot->period_key]);
    }

    /**
     * The snapshot the request asks for, or the newest built when it does not
     * say. A period the site has no snapshot for is a 404, not a fallback:
     * a link to "September" that quietly shows the year is worse than an
     * honest miss.
     */
    protected function chosen(Request $request): ?Snapshot
    {
        $query = Snapshot::query()->where('site', Site::selected()->handle());

        $period = $request->query('period');

        if (is_string($period) && $period !== '') {
            return $query->where('period_key', $period)->first()
                ?? throw new NotFoundHttpException("There is no [{$period}] Wrapped for this site.");
        }

        return $query->orderByDesc('generated_at')->first();
    }
}
