<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\AboutPeople;
use Bpmore\Wrapped\Stats\Concerns\CountsPeople;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;

/**
 * One person, celebrated.
 *
 * **Off unless the site turns it on** (`wrapped.people.individuals`). Naming an
 * individual is the one thing in this addon that can land somebody in a
 * performance conversation, so a site has to decide it is the kind of place
 * where this is a celebration.
 *
 * It returns **exactly one person and their count — never a ranked list, and
 * never anybody at the bottom.** There is no "least active author" card and
 * there will not be one; a full leaderboard invites reading down it. See
 * SPEC.md §3 and the config file.
 *
 * Only the user id is stored. Names and avatars are resolved at display time,
 * so a snapshot never freezes somebody's name into the database.
 */
class TopContributorCard implements AboutPeople, StatCard
{
    use CountsPeople, ReadsPublishedEntries;

    protected const FEWEST_WORTH_SHOWING = 2;

    public function handle(): string
    {
        return 'top_contributor';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        if (! $this->peopleStatsAllowed() || ! $this->individualStatsAllowed()) {
            return null;
        }

        $counts = $this->byAuthor($this->firstPublications($context->events()));

        // With one contributor there is nothing to be top of, and the card
        // would just be that person's name.
        if ($counts->count() < self::FEWEST_WORTH_SHOWING) {
            return null;
        }

        $author = (string) $counts->keys()->first();

        return [
            'author' => $author,
            'count' => $counts->get($author),
        ];
    }
}
