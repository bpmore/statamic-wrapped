<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\Stats\Concerns\DescribesEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;

/**
 * "This page has been quietly doing its job since 2021."
 *
 * Warm, never scolding — SPEC.md §3. This is the page nobody has needed to
 * change, not a page anyone neglected. "You have 600 stale pages" is an
 * accusation and belongs in a product people opted into for exactly that.
 *
 * Found by asking the source for everything it has, oldest event to the end of
 * the period, and taking the entry whose most recent event is furthest back. It
 * only counts if that page went untouched for the whole period: a page last
 * edited two months ago is not a story.
 *
 * **Known limit:** an entry the source has never recorded is invisible here,
 * and it is very likely the most untouched page of all. A site that installed
 * Logbook last year cannot see the page last edited in 2019. "We have no record
 * of it" is not the same as "nobody touched it", so it is left out rather than
 * claimed.
 */
class LongestUntouchedCard implements StatCard
{
    use DescribesEntries;

    public function handle(): string
    {
        return 'longest_untouched';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $earliest = $context->history->source->earliestEvent($context->site);

        if ($earliest === null) {
            return null;
        }

        $lastTouched = $context->events($earliest, $context->to)
            ->filter(fn (HistoryEvent $event) => $event->itemType === 'entry')
            ->groupBy(fn (HistoryEvent $event) => $event->itemId)
            ->map(fn ($events) => $events->max(fn (HistoryEvent $event) => $event->occurredAt->getTimestamp()))
            // Touched during the period, so not untouched.
            ->filter(fn (int $at) => $at < $context->from->getTimestamp())
            ->sort();

        foreach ($lastTouched as $id => $at) {
            $entry = $this->findEntry((string) $id);

            // A page that has since been deleted is not quietly doing its job.
            if ($entry !== null) {
                return $this->describe($entry) + [
                    'last_touched' => $context->from->setTimestamp($at)->toDateString(),
                ];
            }
        }

        return null;
    }
}
