<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Illuminate\Support\Collection;

/**
 * How many entries went out, with last period's number alongside.
 *
 * Needs Partial: file mtimes only ever say a file was written, never that
 * anything was published, so this card is absent on an mtime-only site rather
 * than reporting the date of the last deploy as a publishing spree.
 */
class EntriesPublishedCard implements StatCard
{
    use ReadsPublishedEntries;

    public function handle(): string
    {
        return 'entries_published';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $count = $this->countIn($context->events());

        // Nothing went out. There is no card in that, and "you published 0
        // entries" is a scolding, not a celebration.
        if ($count === 0) {
            return null;
        }

        return [
            'count' => $count,
            // Null rather than 0 for a site younger than one period: it did not
            // publish nothing last year, it did not exist. See SPEC.md §1.
            'previous' => $context->hasHistoryBefore()
                ? $this->countIn($context->events(...$context->previousWindow()))
                : null,
        ];
    }

    /**
     * Distinct entries, not events. An entry unpublished and put back out again
     * was published once as far as a reader is concerned.
     *
     * @param  Collection<int, HistoryEvent>  $events
     */
    protected function countIn(Collection $events): int
    {
        return $this->firstPublications($events)->count();
    }
}
