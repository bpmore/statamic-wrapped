<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;

/**
 * The month the most went out.
 *
 * Stored as a number, not a name: the CP screen translates it. A stored
 * "September" would be wrong on a French site and impossible to change later.
 */
class BusiestMonthCard implements StatCard
{
    use ReadsPublishedEntries;

    public function handle(): string
    {
        return 'busiest_month';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $counts = $this->firstPublications($context->events())
            ->countBy(fn (HistoryEvent $event) => $event->occurredAt->format('Y-m'));

        if ($counts->isEmpty()) {
            return null;
        }

        // sortDesc keeps insertion order among equals and countBy fills in the
        // order events arrive, which is oldest first — so a tie goes to the
        // earlier month rather than an arbitrary one.
        $key = (string) $counts->sortDesc()->keys()->first();
        [$year, $month] = explode('-', $key);

        return [
            'year' => (int) $year,
            'month' => (int) $month,
            'count' => $counts->get($key),
        ];
    }
}
