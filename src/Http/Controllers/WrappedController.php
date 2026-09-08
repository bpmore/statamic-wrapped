<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardPresenter;
use Bpmore\Wrapped\Stats\ConfidenceNotice;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\CP\CpController;

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
    public function index(CardPresenter $presenter, ConfidenceNotice $notice): Response
    {
        $site = Site::selected()->handle();

        $snapshot = Snapshot::query()
            ->where('site', $site)
            ->orderByDesc('generated_at')
            ->first();

        return Inertia::render('wrapped::Wrapped', [
            'site' => $site,
            'snapshot' => $snapshot === null ? null : [
                'period' => $snapshot->period->value,
                'periodKey' => $snapshot->period_key,
                'generatedAt' => $snapshot->generated_at->toIso8601String(),
                'cards' => $presenter->present($snapshot->stats),
                // A Wrapped never appears without saying what it was built
                // from — SPEC.md §1.
                'history' => $notice->for($snapshot),
            ],
        ]);
    }
}
