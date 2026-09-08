<?php

namespace Bpmore\Wrapped\Stats\Concerns;

use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Illuminate\Support\Collection;

/**
 * Picking the "an entry went out" events out of a history stream.
 *
 * Nearly every card is about publishing rather than editing, and every one of
 * them has to make the same two decisions: entries only, and Published only.
 */
trait ReadsPublishedEntries
{
    /**
     * @param  Collection<int, HistoryEvent>  $events
     * @return Collection<int, HistoryEvent>
     */
    protected function publications(Collection $events): Collection
    {
        return $events
            ->filter(fn (HistoryEvent $event) => $event->type === HistoryEventType::Published)
            ->filter(fn (HistoryEvent $event) => $event->itemType === 'entry')
            ->values();
    }

    /**
     * One event per entry, the earliest. An entry unpublished and put back out
     * was published once as far as a reader is concerned, and it belongs in the
     * month it first appeared.
     *
     * @param  Collection<int, HistoryEvent>  $events
     * @return Collection<int, HistoryEvent>
     */
    protected function firstPublications(Collection $events): Collection
    {
        return $this->publications($events)
            ->sortBy(fn (HistoryEvent $event) => $event->occurredAt->getTimestamp())
            ->unique(fn (HistoryEvent $event) => $event->itemId)
            ->values();
    }
}
