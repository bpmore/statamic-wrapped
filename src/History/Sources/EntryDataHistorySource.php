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
 * History inferred from the entries themselves.
 *
 * No audit trail here, only what an entry carries: its `date` on a dated
 * collection, the `updated_at` / `updated_by` pair Statamic writes when
 * `statamic.system.track_last_update` is on, and the blueprint's `author` field
 * if it has one. Timestamps we believe, but no record of what happened between
 * them — which is exactly Confidence::Partial.
 *
 * Two things it cannot tell you, and does not pretend to:
 *
 * - **Nothing was ever created.** Statamic stores no creation timestamp. An
 *   entry's `date` is when it is published or displayed, not when someone first
 *   typed into it.
 * - **Nothing was ever deleted.** Deleted entries are not there to be read.
 *
 * At most two events come out of one entry: it went live, and it was last
 * touched. Every edit before the last one is lost.
 *
 * Caveat worth knowing: on a dated collection, `Entry::date()` falls back to
 * last-modified (and from there to file mtime) for an entry that somehow has no
 * date of its own. That is rare — the date normally comes from the filename —
 * but it is the one way mtime can leak into this source's Partial rating.
 */
class EntryDataHistorySource implements HistorySource
{
    use IteratesEntries;

    /**
     * An entry is evidence it was published once it is actually live. A draft
     * never went out. 'expired' means it did go out and its date has since
     * passed, which is still a publication.
     */
    protected const LIVE = ['published', 'expired'];

    public function handle(): string
    {
        return 'entry_data';
    }

    public function name(): string
    {
        return 'Entry data';
    }

    public function confidence(): Confidence
    {
        return Confidence::Partial;
    }

    /**
     * Decided by whether an entry actually yields something, not by whether
     * entries exist. A site with no dated collections and no `updated_at` has
     * entries but no history in them, and should fall through to mtime rather
     * than resolve here and report nothing.
     */
    public function isAvailable(): bool
    {
        foreach ($this->entries() as $entry) {
            if ($this->eventsFor($entry)->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function events(CarbonImmutable $from, CarbonImmutable $to, ?string $site = null): Collection
    {
        $events = new Collection;

        foreach ($this->entries($site) as $entry) {
            foreach ($this->eventsFor($entry) as $event) {
                if ($event->occurredAt->betweenIncluded($from, $to)) {
                    $events->push($event);
                }
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
            foreach ($this->eventsFor($entry) as $event) {
                if ($earliest === null || $event->occurredAt->lessThan($earliest)) {
                    $earliest = $event->occurredAt;
                }
            }
        }

        return $earliest;
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    protected function eventsFor(EntryModel $entry): Collection
    {
        return (new Collection([
            $this->publishedEvent($entry),
            $this->updatedEvent($entry),
        ]))->filter()->values();
    }

    protected function publishedEvent(EntryModel $entry): ?HistoryEvent
    {
        if (! $entry->collection()?->dated()) {
            return null;
        }

        $date = $entry->date();

        if ($date === null || ! in_array($entry->status(), self::LIVE, true)) {
            return null;
        }

        // A collection whose futureDateBehavior is 'public' — Statamic's
        // default — calls a future-dated entry published. Wrapped reports what
        // has happened, and an entry dated next month has not happened.
        if ($date->isFuture()) {
            return null;
        }

        return $this->event($entry, HistoryEventType::Published, $date, $this->first($entry->get('author')));
    }

    protected function updatedEvent(EntryModel $entry): ?HistoryEvent
    {
        $updatedAt = $entry->get('updated_at');

        if (! is_numeric($updatedAt)) {
            return null;
        }

        $timezone = config('app.timezone');

        return $this->event(
            $entry,
            HistoryEventType::Updated,
            CarbonImmutable::createFromTimestamp((int) $updatedAt, is_string($timezone) ? $timezone : null),
            $this->string($entry->get('updated_by')),
        );
    }

    protected function event(EntryModel $entry, HistoryEventType $type, \DateTimeInterface $at, ?string $author): HistoryEvent
    {
        return new HistoryEvent(
            type: $type,
            itemType: 'entry',
            itemId: (string) $entry->id(),
            occurredAt: CarbonImmutable::instance($at),
            author: $author,
            site: $this->string($entry->locale()),
            meta: array_filter([
                'collection' => $this->string($entry->collectionHandle()),
                'title' => $this->string($entry->get('title')),
                'slug' => $this->string($entry->slug()),
                'status' => $this->string($entry->status()),
            ], fn ($value) => $value !== null),
        );
    }

    /**
     * An `author` field can hold one user or several, depending on the
     * blueprint. One event has one author, so the first is used and the rest
     * are dropped rather than double-counted.
     */
    protected function first(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value === [] ? null : reset($value);
        }

        return $this->string($value);
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
