<?php

use Bpmore\Wrapped\Export\AltText;
use Bpmore\Wrapped\Stats\CardPresenter;
use Statamic\Facades\Site;

function altFor(array $stats, string $handle): string
{
    $snapshot = exportable($stats);
    $card = collect(app(CardPresenter::class)->present($stats))->firstWhere('handle', $handle);

    return app(AltText::class)->forCard($snapshot, $card);
}

it('carries the card\'s own words, so nothing is lost by not seeing it', function () {
    expect(altFor([
        'busiest_time' => ['day' => 2, 'day_count' => 20, 'hour' => 14, 'hour_count' => 18, 'from' => 40],
    ], 'busiest_time'))
        ->toBe('When you publish: You publish most on Tuesday, and most often around 2 pm. From the 2026 Wrapped for Laravel.');
});

it('describes the fact, not the picture', function () {
    $alt = altFor(['entries_published' => ['count' => 47, 'previous' => 21]], 'entries_published');

    expect($alt)->toContain('You published 47 entries')
        ->and(strtolower($alt))->not->toContain('image of')
        ->and(strtolower($alt))->not->toContain('screenshot')
        ->and(strtolower($alt))->not->toContain('picture of');
});

it('names the site as a person knows it, not by its handle', function () {
    // On a single-site install the handle is `default` and the site is
    // called something else; on a multisite one there are several of each.
    // A card that says "for default" names nothing.
    Site::setSites(['default' => ['name' => 'Greenhouse Institute', 'url' => '/', 'locale' => 'en_US']]);

    try {
        expect(altFor(['entries_published' => ['count' => 3, 'previous' => null]], 'entries_published'))
            ->toEndWith('From the 2026 Wrapped for Greenhouse Institute.');
    } finally {
        Site::setSites(['default' => ['name' => 'Laravel', 'url' => '/', 'locale' => 'en_US']]);
    }
});

it('says which wrapped it came from', function () {
    expect(altFor(['entries_published' => ['count' => 3, 'previous' => null]], 'entries_published'))
        ->toEndWith('From the 2026 Wrapped for Laravel.');
});

it('describes the summary with every card on it', function () {
    $stats = [
        'entries_published' => ['count' => 47, 'previous' => 21],
        'busiest_month' => ['year' => 2026, 'month' => 7, 'count' => 12],
    ];

    expect(app(AltText::class)->forSummary(exportable($stats)))
        ->toBe('The 2026 Wrapped for Laravel. Entries published: You published 47 entries, against 21 the period before. Busiest month: July 2026 was your busiest month, with 12 entries.');
});

it('describes an empty wrapped without pretending there is more', function () {
    expect(app(AltText::class)->forSummary(exportable([])))
        ->toBe('The 2026 Wrapped for Laravel.');
});
