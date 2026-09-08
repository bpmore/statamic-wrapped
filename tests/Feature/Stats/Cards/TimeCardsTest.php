<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Cards\BusiestMonthCard;
use Bpmore\Wrapped\Stats\Cards\BusiestTimeCard;
use Bpmore\Wrapped\Stats\Cards\BusiestWeekCard;
use Bpmore\Wrapped\Stats\Cards\LongestStreakCard;

it('all need more than file mtimes', function (string $card, string $handle) {
    expect((new $card)->requires())->toBe(Confidence::Partial)
        ->and((new $card)->handle())->toBe($handle);
})->with([
    [BusiestMonthCard::class, 'busiest_month'],
    [BusiestWeekCard::class, 'busiest_week'],
    [BusiestTimeCard::class, 'busiest_time'],
    [LongestStreakCard::class, 'longest_streak'],
]);

it('all show nothing when nothing was published', function (string $card) {
    expect((new $card)->compute(cardContext([
        wasUpdated('a', '2026-03-04 09:00:00'),
    ])))->toBeNull();
})->with([BusiestMonthCard::class, BusiestWeekCard::class, BusiestTimeCard::class, LongestStreakCard::class]);

describe('busiest month', function () {
    it('finds the month with the most publications', function () {
        $card = (new BusiestMonthCard)->compute(cardContext([
            wasPublished('a', '2026-03-04 09:00:00'),
            wasPublished('b', '2026-07-01 09:00:00'),
            wasPublished('c', '2026-07-14 09:00:00'),
            wasPublished('d', '2026-07-30 09:00:00'),
            wasPublished('e', '2026-11-02 09:00:00'),
        ]));

        expect($card)->toBe(['year' => 2026, 'month' => 7, 'count' => 3]);
    });

    it('gives a tie to the earlier month', function () {
        $card = (new BusiestMonthCard)->compute(cardContext([
            wasPublished('a', '2026-03-04 09:00:00'),
            wasPublished('b', '2026-09-01 09:00:00'),
        ]));

        expect($card['month'])->toBe(3);
    });

    it('counts an entry in the month it first went out, however often it was put out again', function () {
        // Without deduplication September wins with three events. With it,
        // entry a belongs to March and September is left with one.
        $card = (new BusiestMonthCard)->compute(cardContext([
            wasPublished('a', '2026-03-04 09:00:00'),
            wasPublished('b', '2026-03-10 09:00:00'),
            wasPublished('a', '2026-09-01 09:00:00'),
            wasPublished('a', '2026-09-02 09:00:00'),
            wasPublished('c', '2026-09-03 09:00:00'),
        ]));

        expect($card)->toBe(['year' => 2026, 'month' => 3, 'count' => 2]);
    });
});

describe('busiest week', function () {
    it('finds the week with the most publications, keyed on its monday', function () {
        $card = (new BusiestWeekCard)->compute(cardContext([
            wasPublished('a', '2026-03-04 09:00:00'),
            wasPublished('b', '2026-06-30 09:00:00'),
            wasPublished('c', '2026-07-01 09:00:00'),
            wasPublished('d', '2026-07-03 09:00:00'),
        ]));

        expect($card)->toBe(['starts_on' => '2026-06-29', 'week' => 27, 'count' => 3]);
    });

    it('groups a week that crosses a month boundary', function () {
        $card = (new BusiestWeekCard)->compute(cardContext([
            wasPublished('a', '2026-06-30 09:00:00'),
            wasPublished('b', '2026-07-01 09:00:00'),
        ]));

        expect($card['starts_on'])->toBe('2026-06-29')
            ->and($card['count'])->toBe(2);
    });
});

describe('busiest time', function () {
    it('finds the day of the week and the hour, counted separately', function () {
        $card = (new BusiestTimeCard)->compute(cardContext([
            // Tuesdays
            wasPublished('a', '2026-03-03 14:20:00'),
            wasPublished('b', '2026-03-10 09:05:00'),
            wasPublished('c', '2026-03-17 14:45:00'),
            // one Friday
            wasPublished('d', '2026-03-06 14:10:00'),
        ]));

        expect($card['day'])->toBe(2)
            ->and($card['day_count'])->toBe(3)
            ->and($card['hour'])->toBe(14)
            ->and($card['hour_count'])->toBe(3)
            ->and($card['from'])->toBe(4);
    });

    it('ignores dates that carry no time, rather than reporting midnight', function () {
        $card = (new BusiestTimeCard)->compute(cardContext([
            wasPublished('a', '2026-03-02 00:00:00'),
            wasPublished('b', '2026-03-09 00:00:00'),
            wasPublished('c', '2026-03-10 15:00:00'),
        ]));

        expect($card['hour'])->toBe(15)
            ->and($card['from'])->toBe(1);
    });

    it('shows nothing at all when no publication carried a time', function () {
        expect((new BusiestTimeCard)->compute(cardContext([
            wasPublished('a', '2026-03-02 00:00:00'),
            wasPublished('b', '2026-03-09 00:00:00'),
        ])))->toBeNull();
    });
});

describe('longest streak', function () {
    it('counts consecutive days with something published', function () {
        $card = (new LongestStreakCard)->compute(cardContext([
            wasPublished('a', '2026-03-02 09:00:00'),
            wasPublished('b', '2026-03-03 09:00:00'),
            wasPublished('c', '2026-03-04 09:00:00'),
            wasPublished('d', '2026-03-09 09:00:00'),
        ]));

        expect($card)->toBe(['days' => 3, 'from' => '2026-03-02', 'to' => '2026-03-04']);
    });

    it('keeps the longest run, not the last one', function () {
        $card = (new LongestStreakCard)->compute(cardContext([
            wasPublished('a', '2026-03-02 09:00:00'),
            wasPublished('b', '2026-03-03 09:00:00'),
            wasPublished('c', '2026-03-04 09:00:00'),
            wasPublished('d', '2026-03-04 11:00:00'),
            wasPublished('e', '2026-06-01 09:00:00'),
            wasPublished('f', '2026-06-02 09:00:00'),
        ]));

        expect($card)->toBe(['days' => 3, 'from' => '2026-03-02', 'to' => '2026-03-04']);
    });

    it('treats several entries on one day as one busy day, not a streak', function () {
        expect((new LongestStreakCard)->compute(cardContext([
            wasPublished('a', '2026-03-02 09:00:00'),
            wasPublished('b', '2026-03-02 11:00:00'),
            wasPublished('c', '2026-03-02 16:00:00'),
        ])))->toBeNull();
    });

    it('counts a streak that crosses a month boundary', function () {
        $card = (new LongestStreakCard)->compute(cardContext([
            wasPublished('a', '2026-03-30 09:00:00'),
            wasPublished('b', '2026-03-31 09:00:00'),
            wasPublished('c', '2026-04-01 09:00:00'),
        ]));

        expect($card)->toBe(['days' => 3, 'from' => '2026-03-30', 'to' => '2026-04-01']);
    });

    it('shows nothing for a single day of publishing', function () {
        expect((new LongestStreakCard)->compute(cardContext([
            wasPublished('a', '2026-03-02 09:00:00'),
        ])))->toBeNull();
    });
});
