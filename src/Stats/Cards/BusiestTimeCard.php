<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Illuminate\Support\Collection;

/**
 * When in the week things go out — "your team publishes most on Tuesday
 * afternoons", which is the most delightful card in the set (SPEC.md §2).
 *
 * **Events landing exactly on midnight are ignored.** A dated collection stores
 * a date with no time, so `Entry::date()` returns the start of the day, and
 * counting those would report that every site on earth publishes at 00:00.
 * Treating exact midnight as "no time was recorded" costs the occasional real
 * midnight publication and saves the card from being uniformly wrong.
 *
 * The day and the hour are counted independently rather than as a pair. Over a
 * year, a single day-and-hour combination is thin enough to be noise, while
 * "Tuesdays" and "the afternoon" are each well supported — and it still reads
 * as one sentence.
 *
 * Day is ISO: 1 is Monday, 7 is Sunday. Hour is 0-23, local to the site.
 */
class BusiestTimeCard implements StatCard
{
    use ReadsPublishedEntries;

    public function handle(): string
    {
        return 'busiest_time';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $timed = $this->firstPublications($context->events())
            ->reject(fn (HistoryEvent $event) => $event->occurredAt->format('H:i:s') === '00:00:00');

        if ($timed->isEmpty()) {
            return null;
        }

        $days = $timed->countBy(fn (HistoryEvent $event) => $event->occurredAt->dayOfWeekIso);
        $hours = $timed->countBy(fn (HistoryEvent $event) => $event->occurredAt->hour);

        return [
            'day' => (int) $this->winner($days),
            'day_count' => $days->sortDesc()->first(),
            'hour' => (int) $this->winner($hours),
            'hour_count' => $hours->sortDesc()->first(),
            // How many publications carried a time at all. The rest had a date
            // and nothing more, and the UI should not imply otherwise.
            'from' => $timed->count(),
        ];
    }

    /**
     * @param  Collection<array-key, int>  $counts
     */
    protected function winner(Collection $counts): string
    {
        return (string) $counts->sortDesc()->keys()->first();
    }
}
