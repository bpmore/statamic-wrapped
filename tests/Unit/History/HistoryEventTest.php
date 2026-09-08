<?php

use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Carbon\CarbonImmutable;

it('keys an item by type and id', function () {
    $event = new HistoryEvent(
        type: HistoryEventType::Published,
        itemType: 'entry',
        itemId: 'abc-123',
        occurredAt: CarbonImmutable::parse('2026-03-04 09:00:00'),
    );

    expect($event->itemKey())->toBe('entry::abc-123');
});

it('leaves author and site null when the source does not know them', function () {
    $event = new HistoryEvent(
        type: HistoryEventType::Updated,
        itemType: 'entry',
        itemId: 'abc-123',
        occurredAt: CarbonImmutable::parse('2026-03-04 09:00:00'),
    );

    expect($event->author)->toBeNull()
        ->and($event->site)->toBeNull()
        ->and($event->meta)->toBe([]);
});
