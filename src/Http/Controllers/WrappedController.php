<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Export\AltText;
use Bpmore\Wrapped\Export\CardImages;
use Bpmore\Wrapped\Export\Soundtracks;
use Bpmore\Wrapped\Export\VideoSpec;
use Bpmore\Wrapped\Export\WrappedVideo;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardPresenter;
use Bpmore\Wrapped\Stats\ConfidenceNotice;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
    public function index(CardPresenter $presenter, ConfidenceNotice $notice, CardImages $images, AltText $alt, WrappedVideo $video, Soundtracks $soundtracks): Response
    {
        $site = Site::selected()->handle();

        $snapshot = $this->latest();

        return Inertia::render('wrapped::Wrapped', [
            'site' => $site,
            'snapshot' => $snapshot === null ? null : [
                'period' => $snapshot->period->value,
                'periodKey' => $snapshot->period_key,
                // "Since June 2026" for a site younger than the period; the
                // bare period otherwise. SPEC.md §1's first-year framing.
                'label' => $this->label($snapshot),
                'generatedAt' => $snapshot->generated_at->toIso8601String(),
                'cards' => $this->cards($presenter, $alt, $snapshot),
                // A Wrapped never appears without saying what it was built
                // from — SPEC.md §1.
                'history' => $notice->for($snapshot),
                // No browser, no download button. The screen is the real
                // version and works either way.
                'canExport' => $images->isAvailable(),
                'summaryAlt' => $alt->forSummary($snapshot),
                'video' => $this->videoMaker($video, $soundtracks, $snapshot),
                'storyUrl' => cp_route('wrapped.story'),
            ],
        ]);
    }

    /**
     * Each card with the alt text that should go out alongside its image.
     *
     * @return list<array{handle: string, heading: string, body: string, alt: string}>
     */
    protected function cards(CardPresenter $presenter, AltText $alt, Snapshot $snapshot): array
    {
        return array_map(
            fn (array $card) => $card + ['alt' => $alt->forCard($snapshot, $card)],
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
    public function story(CardPresenter $presenter, Soundtracks $soundtracks): Response
    {
        $snapshot = $this->latest();

        if ($snapshot === null) {
            throw new NotFoundHttpException('There is no Wrapped to tell yet.');
        }

        $frames = [['kind' => 'intro', 'title' => __('wrapped::messages.story.intro', ['period' => $this->label($snapshot)]), 'subtitle' => $snapshot->site]];

        foreach ($presenter->present($snapshot->stats) as $card) {
            $frames[] = ['kind' => 'card', 'handle' => $card['handle'], 'heading' => $card['heading'], 'body' => $card['body']];
        }

        $frames[] = ['kind' => 'outro', 'title' => __('wrapped::messages.video.outro')];

        return Inertia::render('wrapped::Story', [
            'site' => $snapshot->site,
            'label' => $this->label($snapshot),
            'backUrl' => cp_route('wrapped.index'),
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
                'period' => $snapshot->period_key,
                'site' => $snapshot->site,
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
        $snapshot = $this->latest();

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
    public function soundtrack(Soundtracks $soundtracks, string $handle): BinaryFileResponse
    {
        $track = $soundtracks->find($handle);

        if ($track === null) {
            throw new NotFoundHttpException("There is no soundtrack called [{$handle}].");
        }

        return response()->file($track->path, [
            'Content-Type' => $track->mimeType(),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * A card as a PNG, downloaded rather than hosted.
     *
     * There is no public URL for a Wrapped and there will not be one: a link
     * leaking a team's internal publishing stats is a support problem and a
     * privacy problem for a free addon — SPEC.md §5.
     */
    public function image(CardImages $images, ?string $card = null): HttpResponse
    {
        $snapshot = $this->latest();

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

    protected function label(Snapshot $snapshot): string
    {
        return $snapshot->isFirstPeriod()
            ? __('wrapped::messages.since', ['month' => $snapshot->started_at->translatedFormat('F Y')])
            : $snapshot->period_key;
    }

    protected function latest(): ?Snapshot
    {
        return Snapshot::query()
            ->where('site', Site::selected()->handle())
            ->orderByDesc('generated_at')
            ->first();
    }
}
