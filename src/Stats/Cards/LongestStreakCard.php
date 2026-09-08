<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Carbon\CarbonImmutable;

/**
 * The longest run of consecutive days with something published.
 *
 * Counted in calendar days, not in entries: three entries on one day is a busy
 * day, not a three-day streak.
 *
 * A streak of one is every site that has ever published anything, so the card
 * only appears from two days upward.
 */
class LongestStreakCard implements StatCard
{
    use ReadsPublishedEntries;

    protected const SHORTEST_WORTH_SHOWING = 2;

    public function handle(): string
    {
        return 'longest_streak';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $days = $this->firstPublications($context->events())
            ->map(fn (HistoryEvent $event) => $event->occurredAt->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($days->isEmpty()) {
            return null;
        }

        $best = ['from' => $days->first(), 'days' => 1];
        $run = ['from' => $days->first(), 'days' => 1];

        foreach ($days->slice(1) as $day) {
            $previous = CarbonImmutable::parse($day)->subDay()->toDateString();

            $run = $previous === $this->lastDayOf($run)
                ? ['from' => $run['from'], 'days' => $run['days'] + 1]
                : ['from' => $day, 'days' => 1];

            if ($run['days'] > $best['days']) {
                $best = $run;
            }
        }

        if ($best['days'] < self::SHORTEST_WORTH_SHOWING) {
            return null;
        }

        return [
            'days' => $best['days'],
            'from' => $best['from'],
            'to' => $this->lastDayOf($best),
        ];
    }

    /**
     * @param  array{from: string, days: int}  $run
     */
    protected function lastDayOf(array $run): string
    {
        return CarbonImmutable::parse($run['from'])->addDays($run['days'] - 1)->toDateString();
    }
}
