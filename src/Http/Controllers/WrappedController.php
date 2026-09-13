<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Export\AltText;
use Bpmore\Wrapped\Export\CardImages;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardPresenter;
use Bpmore\Wrapped\Stats\ConfidenceNotice;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\CP\CpController;
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
    public function index(CardPresenter $presenter, ConfidenceNotice $notice, CardImages $images, AltText $alt): Response
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
