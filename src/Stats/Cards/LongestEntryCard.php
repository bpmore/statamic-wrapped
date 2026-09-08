<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Concerns\DescribesEntries;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Bpmore\Wrapped\Stats\Support\WordCounter;

/**
 * The longest thing published this period.
 *
 * Measured as the entry reads today, with the same caveat as the total word
 * count: nothing can tell us how long it was on the day it went out.
 */
class LongestEntryCard implements StatCard
{
    use DescribesEntries, ReadsPublishedEntries;

    public function __construct(protected WordCounter $words) {}

    public function handle(): string
    {
        return 'longest_entry';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $longest = null;

        foreach ($this->firstPublications($context->events()) as $event) {
            $entry = $this->findEntry($event->itemId);

            if ($entry === null) {
                continue;
            }

            $words = $this->words->forEntry($entry);

            if ($words > 0 && ($longest === null || $words > $longest['words'])) {
                $longest = $this->describe($entry) + ['words' => $words];
            }
        }

        return $longest;
    }
}
