<?php

namespace Bpmore\Wrapped\Stats;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\ResolvedHistory;
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
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?string $site = null,
    ) {}

    public function confidence(): Confidence
    {
        return $this->history->confidence;
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
