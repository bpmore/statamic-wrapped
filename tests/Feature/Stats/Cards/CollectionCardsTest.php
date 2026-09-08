<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Stats\Cards\CollectionWentQuietCard;
use Bpmore\Wrapped\Stats\Cards\FastestGrowingCollectionCard;

/**
 * Every source records the collection on an entry event, so these cards read it
 * from the event's meta rather than resolving the entry.
 */
function inCollection(string $id, string $at, string $collection): HistoryEvent
{
    return wrappedEvent(HistoryEventType::Published, $id, $at, meta: ['collection' => $collection]);
}

it('declares what each collection card needs', function (object $card, string $handle) {
    expect($card->handle())->toBe($handle)
        ->and($card->requires())->toBe(Confidence::Partial);
})->with([
    fn () => [new FastestGrowingCollectionCard, 'fastest_growing_collection'],
    fn () => [new CollectionWentQuietCard, 'collection_went_quiet'],
]);

describe('fastest growing collection', function () {
    it('names the collection that added the most', function () {
        $card = (new FastestGrowingCollectionCard)->compute(cardContext([
            inCollection('a', '2026-02-01 09:00:00', 'blog'),
            inCollection('b', '2026-03-01 09:00:00', 'blog'),
            inCollection('c', '2026-04-01 09:00:00', 'blog'),
            inCollection('d', '2026-05-01 09:00:00', 'recipes'),
        ]));

        expect($card)->toBe(['collection' => 'blog', 'count' => 3, 'previous' => null]);
    });

    it('puts last period alongside', function () {
        $card = (new FastestGrowingCollectionCard)->compute(cardContext([
            inCollection('old-1', '2025-02-01 09:00:00', 'blog'),
            inCollection('old-2', '2025-03-01 09:00:00', 'blog'),
            inCollection('a', '2026-02-01 09:00:00', 'blog'),
            inCollection('b', '2026-03-01 09:00:00', 'blog'),
            inCollection('c', '2026-04-01 09:00:00', 'blog'),
            inCollection('d', '2026-05-01 09:00:00', 'recipes'),
        ]));

        expect($card)->toBe(['collection' => 'blog', 'count' => 3, 'previous' => 2]);
    });

    it('measures growth in entries, not as a ratio', function () {
        // recipes tripled, blog added far more. Blog grew the most.
        $events = [
            inCollection('r-old', '2025-01-01 09:00:00', 'recipes'),
            inCollection('r1', '2026-01-01 09:00:00', 'recipes'),
            inCollection('r2', '2026-01-02 09:00:00', 'recipes'),
            inCollection('r3', '2026-01-03 09:00:00', 'recipes'),
        ];

        foreach (range(1, 20) as $i) {
            $events[] = inCollection("b{$i}", '2026-02-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' 09:00:00', 'blog');
        }

        expect((new FastestGrowingCollectionCard)->compute(cardContext($events))['collection'])->toBe('blog');
    });

    it('shows nothing when only one collection published', function () {
        expect((new FastestGrowingCollectionCard)->compute(cardContext([
            inCollection('a', '2026-02-01 09:00:00', 'blog'),
            inCollection('b', '2026-03-01 09:00:00', 'blog'),
        ])))->toBeNull();
    });

    it('shows nothing when nothing was published', function () {
        expect((new FastestGrowingCollectionCard)->compute(cardContext([])))->toBeNull();
    });

    it('ignores events with no collection on them', function () {
        expect((new FastestGrowingCollectionCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00'),
            inCollection('b', '2026-03-01 09:00:00', 'blog'),
        ])))->toBeNull();
    });
});

describe('collection went quiet', function () {
    it('names a collection that was busy last period and silent this one', function () {
        $card = (new CollectionWentQuietCard)->compute(cardContext([
            inCollection('r1', '2025-02-01 09:00:00', 'recipes'),
            inCollection('r2', '2025-03-01 09:00:00', 'recipes'),
            inCollection('r3', '2025-04-01 09:00:00', 'recipes'),
            inCollection('b1', '2025-05-01 09:00:00', 'blog'),
            inCollection('b2', '2026-02-01 09:00:00', 'blog'),
        ]));

        expect($card)->toBe(['collection' => 'recipes', 'previous' => 3]);
    });

    it('picks the collection that had the most to lose', function () {
        $card = (new CollectionWentQuietCard)->compute(cardContext([
            inCollection('n1', '2025-01-01 09:00:00', 'news'),
            inCollection('n2', '2025-01-02 09:00:00', 'news'),
            inCollection('r1', '2025-02-01 09:00:00', 'recipes'),
            inCollection('r2', '2025-03-01 09:00:00', 'recipes'),
            inCollection('r3', '2025-04-01 09:00:00', 'recipes'),
            inCollection('b1', '2026-02-01 09:00:00', 'blog'),
        ]));

        expect($card['collection'])->toBe('recipes');
    });

    it('does not call a collection quiet when it merely slowed down', function () {
        expect((new CollectionWentQuietCard)->compute(cardContext([
            inCollection('r1', '2025-02-01 09:00:00', 'recipes'),
            inCollection('r2', '2025-03-01 09:00:00', 'recipes'),
            inCollection('r3', '2025-04-01 09:00:00', 'recipes'),
            inCollection('r4', '2026-06-01 09:00:00', 'recipes'),
        ])))->toBeNull();
    });

    it('ignores a collection that only ever published once', function () {
        expect((new CollectionWentQuietCard)->compute(cardContext([
            inCollection('r1', '2025-02-01 09:00:00', 'recipes'),
            inCollection('b1', '2026-02-01 09:00:00', 'blog'),
        ])))->toBeNull();
    });

    it('shows nothing on a site with nothing before this period', function () {
        expect((new CollectionWentQuietCard)->compute(cardContext([
            inCollection('a', '2026-02-01 09:00:00', 'blog'),
            inCollection('b', '2026-03-01 09:00:00', 'blog'),
        ])))->toBeNull();
    });

    it('compares against the previous quarter for a quarterly wrapped', function () {
        $card = (new CollectionWentQuietCard)->compute(
            cardContext(
                [
                    inCollection('r1', '2026-05-01 09:00:00', 'recipes'),
                    inCollection('r2', '2026-06-01 09:00:00', 'recipes'),
                    inCollection('b1', '2026-08-01 09:00:00', 'blog'),
                ],
                period: Period::Quarter,
                from: '2026-07-01 00:00:00',
                to: '2026-09-30 23:59:59',
            )
        );

        expect($card)->toBe(['collection' => 'recipes', 'previous' => 2]);
    });
});
