<?php

namespace Bpmore\Wrapped\Stats;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\ResolvedHistory;
use Bpmore\Wrapped\Snapshots\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What every card is handed: the period, the site, and the history for it.
 *
 * Events are read once and shared. A dozen cards each asking the source for the
 * same window would read the logbook table a dozen times, and on a large site
 * that is the difference between a job and a timeout.
 */
class StatContext
{
    /** @var array<string, Collection<int, HistoryEvent>> */
    protected array $events = [];

    public function __construct(
        public readonly ResolvedHistory $history,
        public readonly Period $period,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?string $site = null,
    ) {}

    public function confidence(): Confidence
    {
        return $this->history->confidence;
    }

    /**
     * The same window, one period earlier: 2025 for a 2026 Wrapped, Q2 for a
     * Q3 one. Shifted by the period rather than by the window's length, so leap
     * years and uneven quarters land on the right dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function previousWindow(): array
    {
        return match ($this->period) {
            Period::Year => [$this->from->subYear(), $this->to->subYear()],
            Period::Quarter => [$this->from->subMonthsNoOverflow(3), $this->to->subMonthsNoOverflow(3)],
        };
    }

    /**
     * Is there history from before this period at all? A site three months old
     * has no last year to compare against, and saying "up from 0" would be a
     * lie about a site that did not exist. See SPEC.md §1.
     */
    public function hasHistoryBefore(): bool
    {
        $earliest = $this->history->source->earliestEvent($this->site);

        return $earliest !== null && $earliest->lessThan($this->from);
    }

    /**
     * Events for this period, or for another window when a card needs one —
     * last year's number alongside this year's, say. Each distinct window is
     * read from the source once.
     *
     * @return Collection<int, HistoryEvent>
     */
    public function events(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection
    {
        $from ??= $this->from;
        $to ??= $this->to;

        $key = $from->getTimestamp().':'.$to->getTimestamp();

        return $this->events[$key] ??= $this->history->source->events($from, $to, $this->site);
    }
}
