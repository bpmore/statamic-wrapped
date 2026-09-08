<?php

namespace Bpmore\Wrapped\Tests\Fixtures;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistorySource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A source with no backing store, so the resolver can be tested on its own.
 */
class FakeHistorySource implements HistorySource
{
    /** How many times the source has actually been read, for cache tests. */
    public int $reads = 0;

    /**
     * @param  list<HistoryEvent>  $events
     */
    public function __construct(
        protected string $handle,
        protected Confidence $confidence,
        protected bool $available = true,
        protected array $events = [],
    ) {}

    public function handle(): string
    {
        return $this->handle;
    }

    public function name(): string
    {
        return ucfirst($this->handle);
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function confidence(): Confidence
    {
        return $this->confidence;
    }

    public function events(CarbonImmutable $from, CarbonImmutable $to, ?string $site = null): Collection
    {
        $this->reads++;

        return (new Collection($this->events))
            ->filter(fn (HistoryEvent $event) => $event->occurredAt->betweenIncluded($from, $to))
            ->filter(fn (HistoryEvent $event) => $site === null || $event->site === $site)
            ->values();
    }

    public function earliestEvent(?string $site = null): ?CarbonImmutable
    {
        return (new Collection($this->events))
            ->filter(fn (HistoryEvent $event) => $site === null || $event->site === $site)
            ->map(fn (HistoryEvent $event) => $event->occurredAt)
            ->sort()
            ->first();
    }
}
