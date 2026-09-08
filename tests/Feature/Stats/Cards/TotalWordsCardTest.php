<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Cards\TotalWordsCard;
use Bpmore\Wrapped\Stats\Support\WordCounter;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;

function wordsCard(): TotalWordsCard
{
    return new TotalWordsCard(new WordCounter);
}

/**
 * @param  array<string, mixed>  $data
 */
function entryWith(string $id, array $data): void
{
    Entry::make()
        ->collection('blog')
        ->id($id)
        ->slug($id)
        ->locale('default')
        ->published(true)
        ->data($data)
        ->save();
}

beforeEach(function () {
    $this->content = sys_get_temp_dir().'/wrapped-words-'.bin2hex(random_bytes(6));

    mkdir($this->content.'/collections', 0777, true);

    Stache::store('collections')->directory($this->content.'/collections');
    Stache::store('entries')->directory($this->content.'/collections');
    Stache::clear();

    Collection::make('blog')->dated(false)->sites(['default'])->save();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->content));
});

it('needs more than file mtimes', function () {
    expect(wordsCard()->requires())->toBe(Confidence::Partial)
        ->and(wordsCard()->handle())->toBe('total_words');
});

it('counts the words in the entries that went out', function () {
    entryWith('a', ['title' => 'One two three']);
    entryWith('b', ['title' => 'Four five']);

    $card = wordsCard()->compute(cardContext([
        wasPublished('a', '2026-02-01 09:00:00'),
        wasPublished('b', '2026-06-01 09:00:00'),
    ]));

    expect($card)->toBe(['words' => 5, 'entries' => 2]);
});

it('counts an entry once however often it was put out', function () {
    entryWith('a', ['title' => 'One two three']);

    $card = wordsCard()->compute(cardContext([
        wasPublished('a', '2026-02-01 09:00:00'),
        wasPublished('a', '2026-06-01 09:00:00'),
    ]));

    expect($card)->toBe(['words' => 3, 'entries' => 1]);
});

it('skips an entry that has since been deleted', function () {
    entryWith('a', ['title' => 'One two three']);

    $card = wordsCard()->compute(cardContext([
        wasPublished('a', '2026-02-01 09:00:00'),
        wasPublished('gone', '2026-06-01 09:00:00'),
    ]));

    expect($card)->toBe(['words' => 3, 'entries' => 1]);
});

it('shows no card when nothing was written', function () {
    expect(wordsCard()->compute(cardContext([])))->toBeNull();
});
