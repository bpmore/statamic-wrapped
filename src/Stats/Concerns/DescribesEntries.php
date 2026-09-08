<?php

namespace Bpmore\Wrapped\Stats\Concerns;

use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as Entries;

/**
 * Turning an entry id from the history into something a card can show.
 *
 * A superlative names one page, so every one of these cards has to look that
 * page up and cope with it having been deleted since.
 */
trait DescribesEntries
{
    protected function findEntry(string $id): ?EntryModel
    {
        $entry = Entries::find($id);

        return $entry instanceof EntryModel ? $entry : null;
    }

    /**
     * The identity of an entry, for the snapshot. No presentation: the title is
     * stored so the card still reads sensibly if the entry is later deleted,
     * and the id so the CP screen can link to it while it exists.
     *
     * @return array{id: string, title: string|null, collection: string|null}
     */
    protected function describe(EntryModel $entry): array
    {
        return [
            'id' => (string) $entry->id(),
            'title' => $this->string($entry->get('title')) ?? $this->string($entry->slug()),
            'collection' => $this->string($entry->collectionHandle()),
        ];
    }

    protected function string(mixed $value): ?string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
