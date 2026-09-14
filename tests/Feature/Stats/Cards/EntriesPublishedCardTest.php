<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Stats\Cards\EntriesPublishedCard;

it('needs more than file mtimes', function () {
    expect((new EntriesPublishedCard)->requires())->toBe(Confidence::Partial)
        ->and((new EntriesPublishedCard)->handle())->toBe('entries_published');
});

it('counts the entries published this period', function () {
    $card = (new EntriesPublishedCard)->compute(cardContext([
        wasPublished('a', '2026-02-01 09:00:00'),
        wasPublished('b', '2026-06-01 09:00:00'),
        wasPublished('c', '2026-09-01 09:00:00'),
    ]));

    expect($card['count'])->toBe(3);
});

it('counts an entry once however often it was put out', function () {
    $card = (new EntriesPublishedCard)->compute(cardContext([
        wasPublished('a', '2026-02-01 09:00:00'),
        wasPublished('a', '2026-06-01 09:00:00'),
    ]));

    expect($card['count'])->toBe(1);
});

it('ignores events that are not entries going out', function () {
    $card = (new EntriesPublishedCard)->compute(cardContext([
        wasPublished('a', '2026-02-01 09:00:00'),
        wasUpdated('b', '2026-03-01 09:00:00'),
        wasPublished('logo.png', '2026-04-01 09:00:00', itemType: 'asset'),
    ]));

    expect($card['count'])->toBe(1);
});

it('puts last year alongside this year', function () {
    $card = (new EntriesPublishedCard)->compute(cardContext([
        wasPublished('old-1', '2025-02-01 09:00:00'),
        wasPublished('old-2', '2025-08-01 09:00:00'),
        wasPublished('a', '2026-02-01 09:00:00'),
        wasPublished('b', '2026-06-01 09:00:00'),
        wasPublished('c', '2026-09-01 09:00:00'),
    ]));

    expect($card)->toBe(['count' => 3, 'previous' => 2]);
});

it('compares against the previous quarter for a quarterly wrapped', function () {
    $card = (new EntriesPublishedCard)->compute(cardContext(
        [
            wasPublished('q2', '2026-05-01 09:00:00'),
            wasPublished('q3-a', '2026-07-05 09:00:00'),
            wasPublished('q3-b', '2026-08-05 09:00:00'),
        ],
        period: Period::Quarter,
        from: '2026-07-01 00:00:00',
        to: '2026-09-30 23:59:59',
    ));

    expect($card)->toBe(['count' => 2, 'previous' => 1]);
});

it('compares against the previous month for a monthly wrapped', function () {
    $card = (new EntriesPublishedCard)->compute(cardContext(
        [
            wasPublished('jul', '2026-07-31 23:00:00'),
            wasPublished('aug-a', '2026-08-01 00:30:00'),
            wasPublished('aug-b', '2026-08-31 23:30:00'),
            wasPublished('sep', '2026-09-01 00:30:00'),
        ],
        period: Period::Month,
        from: '2026-09-01 00:00:00',
        to: '2026-09-30 23:59:59',
    ));

    expect($card)->toBe(['count' => 1, 'previous' => 2]);
});

it('does not say "up from zero" about a site that did not exist yet', function () {
    $card = (new EntriesPublishedCard)->compute(cardContext([
        wasPublished('a', '2026-02-01 09:00:00'),
        wasPublished('b', '2026-06-01 09:00:00'),
    ]));

    expect($card)->toBe(['count' => 2, 'previous' => null]);
});

it('reports a real zero for a site that was quiet last year', function () {
    $card = (new EntriesPublishedCard)->compute(cardContext([
        wasUpdated('old', '2025-01-05 09:00:00'),
        wasPublished('a', '2026-02-01 09:00:00'),
    ]));

    expect($card)->toBe(['count' => 1, 'previous' => 0]);
});

it('shows no card at all rather than telling someone they published nothing', function () {
    expect((new EntriesPublishedCard)->compute(cardContext([
        wasUpdated('a', '2026-02-01 09:00:00'),
    ])))->toBeNull();
});
