<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;

/**
 * The collection that took the period off.
 *
 * Busy last period, silent this one. Warm rather than scolding — SPEC.md §3:
 * this is "your recipes took the year off", not "you neglected your recipes".
 * The CP screen's wording carries that; this card only supplies the fact.
 *
 * Two guards keep it from being noise. The collection has to have gone
 * completely silent, not merely slowed down, and it has to have published at
 * least twice before — one entry last year and none this year is not a
 * collection going quiet, it is a collection that barely started.
 *
 * A site with no history before the period cannot produce this card at all,
 * which is correct: nothing can go quiet before it has spoken.
 */
class CollectionWentQuietCard implements StatCard
{
    use ReadsPublishedEntries;

    protected const FEWEST_WORTH_SHOWING = 2;

    public function handle(): string
    {
        return 'collection_went_quiet';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $now = $this->publicationsByCollection($context->events());
        $before = $this->publicationsByCollection($context->events(...$context->previousWindow()));

        foreach ($before as $collection => $count) {
            if ($count < self::FEWEST_WORTH_SHOWING || $now->has($collection)) {
                continue;
            }

            return [
                'collection' => (string) $collection,
                'previous' => $count,
            ];
        }

        return null;
    }
}
