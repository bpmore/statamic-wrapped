<?php

namespace Bpmore\Wrapped\Widgets;

use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Stats\CardPresenter;
use Bpmore\Wrapped\Stats\ConfidenceNotice;
use Carbon\CarbonImmutable;
use Statamic\Facades\Site;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * The December nudge.
 *
 * "Your 2026 Wrapped is ready" on the dashboard, once, when people are about to
 * go looking for it anyway — SPEC.md §5.
 *
 * Two things it deliberately does not do. It **shows nothing at all when there
 * is no Wrapped**, rather than advertising itself on a dashboard that has
 * nothing to show. And it only calls itself a nudge in December, for the year
 * that is ending: the rest of the year it is a quiet link to something that
 * already exists.
 */
class WrappedWidget extends Widget
{
    protected static $handle = 'wrapped';

    public function __construct(
        protected CardPresenter $presenter,
        protected ConfidenceNotice $notice,
        protected CardGate $gate,
    ) {}

    public function component(): ?VueComponent
    {
        // A nudge towards a screen you cannot open is just a locked door.
        if (! $this->gate->canView()) {
            return null;
        }

        $snapshot = $this->snapshot();

        if ($snapshot === null) {
            return null;
        }

        $nudging = $this->nudging($snapshot);

        return VueComponent::render('wrapped-widget', [
            'key' => $snapshot->period_key,
            'title' => $nudging
                ? __('wrapped::messages.widget.ready', ['period' => Period::label($snapshot->period_key)])
                : __('wrapped::messages.widget.title', ['period' => Period::label($snapshot->period_key)]),
            'headline' => $this->headline($snapshot),
            // The screen's default is its newest build, which on a site
            // that also wraps months may not be this one.
            'url' => cp_route('wrapped.index', ['period' => $snapshot->period_key]),
            'nudge' => $nudging,
        ]);
    }

    /**
     * The newest build, except in December, when the year that is ending wins
     * over whatever month or quarter happened to be built after it. That is
     * the one moment the widget exists for.
     */
    protected function snapshot(): ?Snapshot
    {
        $query = Snapshot::query()->where('site', Site::selected()->handle());

        $now = CarbonImmutable::now();

        if ($now->month === 12) {
            $year = (clone $query)->where('period', Period::Year)->where('period_key', (string) $now->year)->first();

            if ($year !== null) {
                return $year;
            }
        }

        return $query->orderByDesc('generated_at')->first();
    }

    /**
     * December, for the year that is ending. Schedule the generate command for
     * the first, and this is warm by the time anyone looks.
     */
    protected function nudging(Snapshot $snapshot): bool
    {
        return $snapshot->period === Period::Year
            && $snapshot->period_key === (string) CarbonImmutable::now()->year
            && CarbonImmutable::now()->month === 12;
    }

    /**
     * One line. The published count if there is one, because it is the fact
     * people came for; otherwise whatever the Wrapped can honestly say, which
     * on a thin site is the confidence notice rather than nothing.
     */
    protected function headline(Snapshot $snapshot): string
    {
        $cards = $this->presenter->present($snapshot->stats);

        foreach ($cards as $card) {
            if ($card['handle'] === 'entries_published') {
                return $card['body'];
            }
        }

        return $cards === []
            ? $this->notice->for($snapshot)['notice']
            : $cards[0]['body'];
    }
}
