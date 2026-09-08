<?php

namespace Bpmore\Wrapped\History\Sources\Concerns;

use Illuminate\Support\LazyCollection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as Entries;
use Statamic\Query\Builder as QueryBuilder;

/**
 * Walking every entry, one chunk at a time.
 *
 * Shared by the two sources that read entries rather than a log — a Wrapped is
 * generated over a whole site, so neither of them can afford to load 40,000
 * entries into memory at once.
 */
trait IteratesEntries
{
    /**
     * @return LazyCollection<int, EntryModel>
     */
    protected function entries(?string $site = null): LazyCollection
    {
        $query = Entries::query();

        // Statamic's entry query contract is empty, and a content driver is
        // free to return anything for it. One we cannot drive yields nothing,
        // which makes the source unavailable and lets the resolver fall
        // through to a source that works.
        if (! $query instanceof QueryBuilder) {
            return LazyCollection::empty();
        }

        if ($site !== null) {
            $query->where('site', $site);
        }

        return $query->lazy();
    }
}
