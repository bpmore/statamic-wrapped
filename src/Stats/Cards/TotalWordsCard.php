<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Bpmore\Wrapped\Stats\Support\WordCounter;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as Entries;

/**
 * How many words went out this period.
 *
 * Counted from the entries as they stand today, not as they stood when they
 * were published. An entry published in January and rewritten in June counts at
 * its June length — there is no source that can give us the January one, and
 * pretending otherwise would be worse than the approximation.
 *
 * Entries published and since deleted cannot be counted at all.
 */
class TotalWordsCard implements StatCard
{
    use ReadsPublishedEntries;

    public function __construct(protected WordCounter $words) {}

    public function handle(): string
    {
        return 'total_words';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $words = 0;
        $counted = 0;

        foreach ($this->publishedIds($context) as $id) {
            $entry = Entries::find($id);

            if (! $entry instanceof EntryModel) {
                continue;
            }

            $words += $this->words->forEntry($entry);
            $counted++;
        }

        if ($words === 0) {
            return null;
        }

        return [
            'words' => $words,
            // How many entries that total is drawn from, which is not
            // necessarily the published count: deleted ones cannot be read.
            'entries' => $counted,
        ];
    }

    /**
     * @return list<string>
     */
    protected function publishedIds(StatContext $context): array
    {
        return $this->firstPublications($context->events())
            ->map(fn (HistoryEvent $event) => $event->itemId)
            ->values()
            ->all();
    }
}
