<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\StatCardRegistry;
use Bpmore\Wrapped\Tests\Fixtures\FakeStatCard;

it('holds the cards it was given, in registration order', function () {
    $registry = new StatCardRegistry([
        new FakeStatCard('entries_published'),
        new FakeStatCard('busiest_day'),
    ]);

    expect($registry->all()->keys()->all())->toBe(['entries_published', 'busiest_day']);
});

it('starts empty', function () {
    expect((new StatCardRegistry)->all())->toBeEmpty();
});

it('finds a card by handle', function () {
    $card = new FakeStatCard('entries_published');

    expect((new StatCardRegistry([$card]))->find('entries_published'))->toBe($card);
});

it('finds nothing for a handle it does not have', function () {
    expect((new StatCardRegistry)->find('nope'))->toBeNull();
});

it('refuses two cards with the same handle, which would overwrite each other', function () {
    $registry = new StatCardRegistry([new FakeStatCard('entries_published')]);

    expect(fn () => $registry->register(new FakeStatCard('entries_published')))
        ->toThrow(InvalidArgumentException::class, 'already registered as [entries_published]');
});

it('lets every card say what history it needs', function () {
    $registry = new StatCardRegistry([
        new FakeStatCard('most_revised', Confidence::High),
        new FakeStatCard('busiest_month', Confidence::Partial),
        new FakeStatCard('last_touched', Confidence::Low),
    ]);

    expect($registry->all()->map->requires()->all())->toBe([
        'most_revised' => Confidence::High,
        'busiest_month' => Confidence::Partial,
        'last_touched' => Confidence::Low,
    ]);
});
