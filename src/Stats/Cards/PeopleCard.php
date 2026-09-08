<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\Stats\Concerns\CountsPeople;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;

/**
 * How many people it took, without naming any of them.
 *
 * This is the people card that is on by default, because it cannot single
 * anybody out: "42 entries from 7 people" is a fact about a team. Turn it off
 * entirely with `wrapped.people.enabled` — see the config file for why that
 * switch exists.
 *
 * Needs two people. A team card about one person is not a team card, and on a
 * one-person site it identifies that person by arithmetic.
 */
class PeopleCard implements StatCard
{
    use CountsPeople, ReadsPublishedEntries;

    protected const FEWEST_WORTH_SHOWING = 2;

    public function handle(): string
    {
        return 'people';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        if (! $this->peopleStatsAllowed()) {
            return null;
        }

        $publications = $this->firstPublications($context->events());
        $counts = $this->byAuthor($publications);

        if ($counts->count() < self::FEWEST_WORTH_SHOWING) {
            return null;
        }

        return [
            'people' => $counts->count(),
            // Only the publications we could attribute to somebody, which is
            // not always the published total.
            'entries' => (int) $counts->sum(),
            'unattributed' => $publications
                ->filter(fn (HistoryEvent $event) => $event->author === null)
                ->count(),
        ];
    }
}
