<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;

/**
 * The collection that grew the most.
 *
 * "Most" is a count, not a ratio. A ratio would let a collection that went from
 * one entry to three beat one that went from fifty to a hundred and twenty,
 * which is not what anyone means by "grew the most". `previous` is reported
 * alongside so the screen can still say "up from 31" if it wants to.
 *
 * Needs at least two collections to have published something. On a site with
 * one collection the answer is trivially that collection, and a card whose
 * answer was never in doubt is not worth the space.
 */
class FastestGrowingCollectionCard implements StatCard
{
    use ReadsPublishedEntries;

    protected const FEWEST_COLLECTIONS_WORTH_COMPARING = 2;

    public function handle(): string
    {
        return 'fastest_growing_collection';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $counts = $this->publicationsByCollection($context->events());

        if ($counts->count() < self::FEWEST_COLLECTIONS_WORTH_COMPARING) {
            return null;
        }

        $collection = (string) $counts->keys()->first();

        return [
            'collection' => $collection,
            'count' => $counts->get($collection),
            // Null rather than 0 for a site younger than one period: it did not
            // publish nothing last year, it did not exist.
            'previous' => $context->hasHistoryBefore()
                ? $this->publicationsByCollection($context->events(...$context->previousWindow()))->get($collection, 0)
                : null,
        ];
    }
}
