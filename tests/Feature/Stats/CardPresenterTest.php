<?php

use Bpmore\Wrapped\Stats\CardPresenter;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

function presented(array $stats): array
{
    return app(CardPresenter::class)->present($stats);
}

// The presenter gates people cards on the viewer. Everything here is about
// wording, so act as someone who may see all of it.
beforeEach(fn () => test()->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper()));

function bodyOf(string $handle, array $data): string
{
    return presented([$handle => $data])[0]['body'];
}

it('names a month rather than printing its number', function () {
    expect(bodyOf('busiest_month', ['year' => 2026, 'month' => 7, 'count' => 12]))
        ->toBe('July 2026 was your busiest month, with 12 entries.');
});

it('names a day and a readable hour rather than printing 2 and 14', function () {
    expect(bodyOf('busiest_time', ['day' => 2, 'day_count' => 9, 'hour' => 14, 'hour_count' => 7, 'from' => 20]))
        ->toBe('You publish most on Tuesday, and most often around 2 pm.');
});

it('reads sunday as the last day of the week, not the first', function () {
    expect(bodyOf('busiest_time', ['day' => 7, 'day_count' => 3, 'hour' => 9, 'hour_count' => 3, 'from' => 5]))
        ->toContain('Sunday');
});

it('writes a file size in units a person reads', function () {
    expect(bodyOf('assets_uploaded', ['count' => 12, 'bytes' => 5242880, 'weighed' => 12]))
        ->toBe('12 files, 5.0 MB in all.');
});

it('writes a turnaround as a duration, not a pile of seconds', function () {
    expect(bodyOf('fastest_turnaround', [
        'id' => 'x', 'title' => 'Quick One', 'collection' => 'blog', 'seconds' => 2520,
    ]))->toBe('"Quick One" went from first draft to published in 42 minutes.');
});

it('separates thousands', function () {
    expect(bodyOf('total_words', ['words' => 1234567, 'entries' => 89]))
        ->toBe('1,234,567 words, across 89 entries.');
});

it('writes dates out in full', function () {
    expect(bodyOf('longest_streak', ['days' => 9, 'from' => '2026-03-02', 'to' => '2026-03-10']))
        ->toBe('9 days running, from 2 March 2026 to 10 March 2026.');
});

it('keeps the warm wording for a page nobody has touched', function () {
    $card = presented(['longest_untouched' => [
        'id' => 'about', 'title' => 'About Us', 'collection' => 'pages', 'last_touched' => '2021-06-04',
    ]])[0];

    expect($card['heading'])->toBe('Quietly doing its job')
        ->and($card['body'])->toBe('"About Us" has needed no changes since 4 June 2021.');
});

it('resolves a contributor id to a name at display time', function () {
    $content = sys_get_temp_dir().'/wrapped-users-'.bin2hex(random_bytes(6));
    mkdir($content, 0777, true);

    Stache::store('users')->directory($content);
    Stache::clear();

    User::make()->id('ada')->email('ada@example.com')->data(['name' => 'Ada Lovelace'])->save();

    expect(bodyOf('top_contributor', ['author' => 'ada', 'count' => 12]))
        ->toBe('Ada Lovelace published 12 entries.');

    exec('rm -rf '.escapeshellarg($content));
});

it('never falls back to an email for a contributor with no name', function () {
    // The same sentence goes on a public page, so a missing name must read
    // like a deleted user, not leak the address behind the account.
    $content = sys_get_temp_dir().'/wrapped-users-'.bin2hex(random_bytes(6));
    mkdir($content, 0777, true);

    Stache::store('users')->directory($content);
    Stache::clear();

    User::make()->id('nameless')->email('private@example.com')->save();

    $body = bodyOf('top_contributor', ['author' => 'nameless', 'count' => 12]);

    expect($body)->toBe(' published 12 entries.')
        ->and($body)->not->toContain('private@example.com')
        ->and($body)->not->toContain('@');

    exec('rm -rf '.escapeshellarg($content));
});

it('does not show a bare user id for a contributor who has left', function () {
    expect(bodyOf('top_contributor', ['author' => 'someone-deleted', 'count' => 12]))
        ->toBe(' published 12 entries.');
});

it('presents every card this addon ships', function () {
    // Each handle here must have wording, or present() silently drops it.
    $stats = [
        'entries_published' => ['count' => 3, 'previous' => null],
        'total_words' => ['words' => 100, 'entries' => 3],
        'assets_uploaded' => ['count' => 2, 'bytes' => 1024, 'weighed' => 2],
        'busiest_month' => ['year' => 2026, 'month' => 3, 'count' => 2],
        'busiest_week' => ['starts_on' => '2026-06-29', 'week' => 27, 'count' => 3],
        'busiest_time' => ['day' => 2, 'day_count' => 3, 'hour' => 14, 'hour_count' => 3, 'from' => 3],
        'longest_streak' => ['days' => 3, 'from' => '2026-03-02', 'to' => '2026-03-04'],
        'longest_entry' => ['id' => 'a', 'title' => 'A', 'collection' => 'blog', 'words' => 900],
        'most_revised' => ['id' => 'b', 'title' => 'B', 'collection' => 'blog', 'edits' => 47],
        'fastest_turnaround' => ['id' => 'c', 'title' => 'C', 'collection' => 'blog', 'seconds' => 600],
        'top_taxonomy_term' => ['taxonomy' => 'topics', 'term' => 'design', 'count' => 5],
        'longest_untouched' => ['id' => 'd', 'title' => 'D', 'collection' => 'pages', 'last_touched' => '2021-06-04'],
        'fastest_growing_collection' => ['collection' => 'blog', 'count' => 3, 'previous' => 1],
        'collection_went_quiet' => ['collection' => 'recipes', 'previous' => 4],
        'people' => ['people' => 3, 'entries' => 3, 'unattributed' => 0],
        'top_contributor' => ['author' => 'ada', 'count' => 2],
    ];

    $cards = presented($stats);

    expect($cards)->toHaveCount(count($stats));

    foreach ($cards as $card) {
        expect($card['heading'])->not->toContain('wrapped::')
            ->and($card['body'])->not->toContain('wrapped::')
            ->and($card['body'])->not->toContain(':');
    }
});
