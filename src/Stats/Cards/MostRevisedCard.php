<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\Stats\Concerns\DescribesEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;

/**
 * "This page was edited 47 times."
 *
 * Requires only Partial, but **guards itself on the data rather than on the
 * rating**. Entry data and file mtimes can only ever produce one Updated event
 * per entry, so the winner would be "edited once" — which is not a superlative
 * and not worth a card. Anything below two edits returns nothing, which means
 * the card quietly disappears on a source that cannot count edits and works
 * properly on Revisions (Partial) as well as Logbook (High).
 *
 * That is deliberately not a High requirement: revisions answer this question
 * perfectly well, and gating on confidence alone would drop the card on the
 * sites best able to support it.
 *
 * Publishing is not counted as an edit. It is a status change, and it has cards
 * of its own.
 */
class MostRevisedCard implements StatCard
{
    use DescribesEntries;

    protected const FEWEST_WORTH_SHOWING = 2;

    public function handle(): string
    {
        return 'most_revised';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $counts = $context->events()
            ->filter(fn (HistoryEvent $event) => $event->type === HistoryEventType::Updated)
            ->filter(fn (HistoryEvent $event) => $event->itemType === 'entry')
            ->countBy(fn (HistoryEvent $event) => $event->itemId)
            ->sortDesc();

        foreach ($counts as $id => $edits) {
            if ($edits < self::FEWEST_WORTH_SHOWING) {
                return null;
            }

            $entry = $this->findEntry((string) $id);

            // Walk on rather than stop: the most-edited page of the year may
            // have been deleted since, and the runner-up is still a good card.
            if ($entry !== null) {
                return $this->describe($entry) + ['edits' => $edits];
            }
        }

        return null;
    }
}
