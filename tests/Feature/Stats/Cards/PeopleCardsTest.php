<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Cards\PeopleCard;
use Bpmore\Wrapped\Stats\Cards\TopContributorCard;
use Bpmore\Wrapped\Stats\StatCardRegistry;

function byThem(): array
{
    return [
        wasPublished('a', '2026-02-01 09:00:00', author: 'ada'),
        wasPublished('b', '2026-03-01 09:00:00', author: 'ada'),
        wasPublished('c', '2026-04-01 09:00:00', author: 'ada'),
        wasPublished('d', '2026-05-01 09:00:00', author: 'grace'),
        wasPublished('e', '2026-06-01 09:00:00', author: 'grace'),
        wasPublished('f', '2026-07-01 09:00:00', author: 'linus'),
    ];
}

it('declares what each people card needs', function (object $card, string $handle) {
    expect($card->handle())->toBe($handle)
        ->and($card->requires())->toBe(Confidence::Partial);
})->with([
    fn () => [new PeopleCard, 'people'],
    fn () => [new TopContributorCard, 'top_contributor'],
]);

describe('the team card', function () {
    it('is on without anyone opting in', function () {
        expect(config('wrapped.people.enabled'))->toBeTrue();

        $card = (new PeopleCard)->compute(cardContext(byThem()));

        expect($card)->toBe(['people' => 3, 'entries' => 6, 'unattributed' => 0]);
    });

    it('names nobody', function () {
        $card = (new PeopleCard)->compute(cardContext(byThem()));

        expect(implode(' ', array_keys($card)))->not->toContain('author')
            ->and(json_encode($card))->not->toContain('ada');
    });

    it('says how many publications it could not attribute', function () {
        $card = (new PeopleCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00', author: 'ada'),
            wasPublished('b', '2026-03-01 09:00:00', author: 'grace'),
            wasPublished('c', '2026-04-01 09:00:00'),
        ]));

        expect($card)->toBe(['people' => 2, 'entries' => 2, 'unattributed' => 1]);
    });

    it('disappears when the site switches people off', function () {
        config(['wrapped.people.enabled' => false]);

        expect((new PeopleCard)->compute(cardContext(byThem())))->toBeNull();
    });

    it('shows nothing on a one-person site, which would identify them', function () {
        expect((new PeopleCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00', author: 'ada'),
            wasPublished('b', '2026-03-01 09:00:00', author: 'ada'),
        ])))->toBeNull();
    });

    it('shows nothing when no source recorded an author', function () {
        expect((new PeopleCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00'),
            wasPublished('b', '2026-03-01 09:00:00'),
        ])))->toBeNull();
    });
});

describe('the top contributor card', function () {
    it('is off until the site opts in', function () {
        expect(config('wrapped.people.individuals'))->toBeFalse()
            ->and((new TopContributorCard)->compute(cardContext(byThem())))->toBeNull();
    });

    it('celebrates one person once opted in', function () {
        config(['wrapped.people.individuals' => true]);

        expect((new TopContributorCard)->compute(cardContext(byThem())))
            ->toBe(['author' => 'ada', 'count' => 3]);
    });

    it('never ranks anyone below the top, so nobody can read down the list', function () {
        config(['wrapped.people.individuals' => true]);

        $card = (new TopContributorCard)->compute(cardContext(byThem()));

        expect($card)->toHaveCount(2)
            ->and(json_encode($card))->not->toContain('grace')
            ->and(json_encode($card))->not->toContain('linus');
    });

    it('stores a user id, never a name frozen into the database', function () {
        config(['wrapped.people.individuals' => true]);

        expect((new TopContributorCard)->compute(cardContext(byThem()))['author'])->toBe('ada');
    });

    it('stays off when people are switched off entirely, even if opted in', function () {
        config([
            'wrapped.people.enabled' => false,
            'wrapped.people.individuals' => true,
        ]);

        expect((new TopContributorCard)->compute(cardContext(byThem())))->toBeNull();
    });

    it('shows nothing when there is only one contributor to be top of', function () {
        config(['wrapped.people.individuals' => true]);

        expect((new TopContributorCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00', author: 'ada'),
            wasPublished('b', '2026-03-01 09:00:00', author: 'ada'),
        ])))->toBeNull();
    });
});

it('ships no card that ranks people from the bottom', function () {
    // A guard on the product decision in SPEC.md §3, not on an implementation
    // detail: if a "least active" card is ever added, this is where it stops.
    $handles = app(StatCardRegistry::class)->all()->keys();

    expect($handles->filter(fn (string $handle) => str_contains($handle, 'least')
        || str_contains($handle, 'quietest')
        || str_contains($handle, 'leaderboard')
        || str_contains($handle, 'ranking'))->all())->toBe([]);
});
