<?php

use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Stats\CardPresenter;
use Bpmore\Wrapped\Stats\Voice;
use Statamic\Facades\User;

/*
 * Two voices: "you" for the editor in the control panel, "we" for a public
 * page where the site speaks for itself. The plain translation keys are the
 * "you" wording; a `_we` key beside one is the "we" wording, and a line with
 * no pronoun in it has no `_we` key at all.
 */

beforeEach(fn () => test()->actingAs(User::make()->id('super')->email('super@example.com')->makeSuper()));

function inVoice(string $handle, array $data, Voice $voice): array
{
    return app(CardPresenter::class)->present([$handle => $data], $voice)[0];
}

it('says you in the control panel, as it always has', function () {
    expect(inVoice('entries_published', ['count' => 42, 'previous' => null], Voice::You)['body'])
        ->toBe('You published 42 entries.');
});

it('says we on a public page', function () {
    expect(inVoice('entries_published', ['count' => 42, 'previous' => null], Voice::We)['body'])
        ->toBe('We published 42 entries.');
});

it('changes a heading too, when the heading says you', function () {
    $card = inVoice('busiest_time', ['day' => 2, 'day_count' => 9, 'hour' => 14, 'hour_count' => 7, 'from' => 20], Voice::We);

    expect($card['heading'])->toBe('When we publish')
        ->and($card['body'])->toBe('We publish most on Tuesday, and most often around 2 pm.');
});

it('leaves a line alone when nobody is addressed in it', function () {
    expect(inVoice('total_words', ['words' => 1234, 'entries' => 5], Voice::We)['body'])
        ->toBe('1,234 words, across 5 entries.');
});

it('closes in the same voice as the cards', function () {
    expect(Period::outro('2026', Voice::We))->toBe('That was our year.')
        ->and(Period::outro('2026-Q3', Voice::We))->toBe('That was our quarter.')
        ->and(Period::outro('2026-08', Voice::We))->toBe('That was our month.')
        ->and(Period::outro('2026'))->toBe('That was your year.');
});

// The sweep. Every line a public page can show, in the "we" voice, with
// nothing left over that talks to the editor. A new card with a "you" in it
// and no `_we` key fails here, which is the point.
it('leaves no you or your on a public page', function () {
    /** @var array<string, array<string, string>> $cards */
    $cards = require __DIR__.'/../../../resources/lang/en/cards.php';

    $lines = [];

    foreach ($cards as $handle => $keys) {
        foreach (['heading', 'body', 'compared'] as $key) {
            if (isset($keys[$key])) {
                $lines["cards.{$handle}.{$key}"] = __('wrapped::'.Voice::We->key("cards.{$handle}.{$key}"));
            }
        }
    }

    foreach (Period::cases() as $period) {
        $lines["outro.{$period->value}"] = __('wrapped::'.Voice::We->key("messages.video.outro.{$period->value}"));
    }

    expect($lines)->not->toBeEmpty();

    foreach ($lines as $key => $line) {
        expect($line)->not->toMatch('/\b(you|your|yours)\b/i', "[{$key}] still talks to the editor: \"{$line}\"");
    }
});

it('has no we wording that is not paired with a you wording', function () {
    /** @var array<string, array<string, string>> $cards */
    $cards = require __DIR__.'/../../../resources/lang/en/cards.php';

    foreach ($cards as $handle => $keys) {
        foreach (array_keys($keys) as $key) {
            if (str_ends_with($key, '_we')) {
                expect(array_key_exists(substr($key, 0, -3), $keys))->toBeTrue("[cards.{$handle}.{$key}] has no plain key beside it");
            }
        }
    }
});
