<?php

namespace Bpmore\Wrapped\Stats\Concerns;

use Bpmore\Wrapped\History\HistoryEvent;
use Illuminate\Support\Collection;

/**
 * The two questions every people card has to ask first: is this allowed, and
 * who did what.
 */
trait CountsPeople
{
    /**
     * Team-level stats. On by default; a site can switch people out of its
     * Wrapped entirely.
     */
    protected function peopleStatsAllowed(): bool
    {
        return (bool) config('wrapped.people.enabled', true);
    }

    /**
     * Stats that name one person. **Off by default, opt-in on purpose.**
     */
    protected function individualStatsAllowed(): bool
    {
        return (bool) config('wrapped.people.individuals', false);
    }

    /**
     * Publications per author, busiest first.
     *
     * Events with no author are dropped rather than gathered under an "unknown"
     * bucket — an unknown bucket can win, and "your top contributor is nobody"
     * is not a card. Cards that care report the unattributed count separately.
     *
     * @param  Collection<int, HistoryEvent>  $events
     * @return Collection<string, int>
     */
    protected function byAuthor(Collection $events): Collection
    {
        return $events
            ->map(fn (HistoryEvent $event) => $event->author)
            ->filter(fn (?string $author) => $author !== null && $author !== '')
            ->countBy()
            ->sortDesc();
    }
}
