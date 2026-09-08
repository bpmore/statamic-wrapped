<?php

namespace Bpmore\Wrapped\History;

use Carbon\CarbonImmutable;

/**
 * One thing that happened to one piece of content, at one moment.
 *
 * Every source flattens whatever it knows into a stream of these, so the stat
 * layer never has to care which source it is reading from.
 */
class HistoryEvent
{
    /**
     * @param  string  $itemType  What kind of thing this happened to: 'entry', 'asset' or 'term'.
     * @param  string  $itemId  The item's id, unique within its type.
     * @param  string|null  $author  User id, when the source knows it. Sources must not guess.
     * @param  string|null  $site  Site handle, when the source knows it.
     * @param  array<string, mixed>  $meta  Anything extra a source can offer, e.g. a collection handle.
     */
    public function __construct(
        public readonly HistoryEventType $type,
        public readonly string $itemType,
        public readonly string $itemId,
        public readonly CarbonImmutable $occurredAt,
        public readonly ?string $author = null,
        public readonly ?string $site = null,
        public readonly array $meta = [],
    ) {}

    /**
     * A key that identifies the item this event is about, across types.
     */
    public function itemKey(): string
    {
        return $this->itemType.'::'.$this->itemId;
    }
}
