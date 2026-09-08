<?php

namespace Bpmore\Wrapped\Http\Controllers;

use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardPresenter;
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
    public function index(CardPresenter $presenter): Response
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
                'historySource' => $snapshot->history_source,
                'confidence' => $snapshot->confidence->value,
                'generatedAt' => $snapshot->generated_at->toIso8601String(),
                'cards' => $presenter->present($snapshot->stats),
            ],
        ]);
    }
}
