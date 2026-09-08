<?php

namespace Bpmore\Wrapped\History\Sources;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\HistorySource;
use Bpmore\Wrapped\History\Sources\Concerns\IteratesEntries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;

/**
 * The last resort: when a file was last written.
 *
 * This is the source that exists so a Wrapped can say "we don't really know"
 * rather than nothing at all. A git checkout, an rsync or most deploys rewrite
 * every content file, so on a deployed site these timestamps are frequently the
 * date of the last deploy and nothing more.
 *
 * Three deliberate limits, all of them the point rather than an omission:
 *
 * - **Updated events only.** An mtime says a file was written. It does not say
 *   the entry was published, and reporting a publication from one is exactly the
 *   "busiest week that's actually the date of the last deploy" that SPEC.md §1
 *   warns about.
 * - **No author, ever.** A filesystem does not record who.
 * - **One event per entry.** Only the most recent write survives.
 *
 * Note this reads the mtime off the filesystem itself rather than calling
 * Statamic's `fileLastModified()`, which returns *now* for a file that does not
 * exist. A missing file must produce no event, not an event dated today.
 */
class MtimeHistorySource implements HistorySource
{
    use IteratesEntries;

    public function handle(): string
    {
        return 'mtime';
    }

    public function name(): string
    {
        return 'File modification times';
    }

    public function confidence(): Confidence
    {
        return Confidence::Low;
    }

    public function isAvailable(): bool
    {
        foreach ($this->entries() as $entry) {
            if ($this->event($entry) !== null) {
                return true;
            }
        }

        return false;
    }

    public function events(CarbonImmutable $from, CarbonImmutable $to, ?string $site = null): Collection
    {
        $events = new Collection;

        foreach ($this->entries($site) as $entry) {
            $event = $this->event($entry);

            if ($event && $event->occurredAt->betweenIncluded($from, $to)) {
                $events->push($event);
            }
        }

        return $events
            ->sortBy(fn (HistoryEvent $event) => $event->occurredAt->getTimestamp())
            ->values();
    }

    public function earliestEvent(?string $site = null): ?CarbonImmutable
    {
        $earliest = null;

        foreach ($this->entries($site) as $entry) {
            $event = $this->event($entry);

            if ($event && ($earliest === null || $event->occurredAt->lessThan($earliest))) {
                $earliest = $event->occurredAt;
            }
        }

        return $earliest;
    }

    protected function event(EntryModel $entry): ?HistoryEvent
    {
        $modified = $this->lastModified($entry);

        if ($modified === null) {
            return null;
        }

        return new HistoryEvent(
            type: HistoryEventType::Updated,
            itemType: 'entry',
            itemId: (string) $entry->id(),
            occurredAt: $modified,
            author: null,
            site: $this->string($entry->locale()),
            meta: array_filter([
                'collection' => $this->string($entry->collectionHandle()),
                'title' => $this->string($entry->get('title')),
                'slug' => $this->string($entry->slug()),
            ], fn ($value) => $value !== null),
        );
    }

    /**
     * Null for an entry with no file behind it — an eloquent-driver entry, or
     * one whose file has been moved out from under the Stache.
     */
    protected function lastModified(EntryModel $entry): ?CarbonImmutable
    {
        $path = $entry->path();

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $modified = filemtime($path);

        if ($modified === false) {
            return null;
        }

        $timezone = config('app.timezone');

        return CarbonImmutable::createFromTimestamp($modified, is_string($timezone) ? $timezone : null);
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
