<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Cards\FastestTurnaroundCard;
use Bpmore\Wrapped\Stats\Cards\LongestEntryCard;
use Bpmore\Wrapped\Stats\Cards\LongestUntouchedCard;
use Bpmore\Wrapped\Stats\Cards\MostRevisedCard;
use Bpmore\Wrapped\Stats\Cards\TopTaxonomyTermCard;
use Bpmore\Wrapped\Stats\Support\WordCounter;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\Taxonomy;

/**
 * @param  array<string, mixed>  $data
 */
function page(string $id, array $data = [], string $collection = 'blog'): void
{
    Entry::make()
        ->collection($collection)
        ->id($id)
        ->slug($id)
        ->locale('default')
        ->published(true)
        ->data($data + ['title' => ucfirst(str_replace('-', ' ', $id))])
        ->save();
}

beforeEach(function () {
    $this->content = sys_get_temp_dir().'/wrapped-super-'.bin2hex(random_bytes(6));

    mkdir($this->content.'/collections', 0777, true);
    mkdir($this->content.'/taxonomies', 0777, true);

    Stache::store('collections')->directory($this->content.'/collections');
    Stache::store('entries')->directory($this->content.'/collections');
    Stache::store('taxonomies')->directory($this->content.'/taxonomies');
    Stache::store('terms')->directory($this->content.'/taxonomies');
    Stache::clear();

    Taxonomy::make('topics')->save();
    Collection::make('blog')->dated(false)->sites(['default'])->save();

    Blueprint::make('post')->setNamespace('collections.blog')->setContents([
        'fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'body', 'field' => ['type' => 'markdown']],
            ['handle' => 'topics', 'field' => ['type' => 'terms', 'taxonomies' => ['topics']]],
        ],
    ])->save();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->content));
});

it('declares what each superlative needs', function (object $card, string $handle, Confidence $requires) {
    expect($card->handle())->toBe($handle)
        ->and($card->requires())->toBe($requires);
})->with([
    fn () => [new LongestEntryCard(new WordCounter), 'longest_entry', Confidence::Partial],
    fn () => [new MostRevisedCard, 'most_revised', Confidence::Partial],
    fn () => [new FastestTurnaroundCard, 'fastest_turnaround', Confidence::High],
    fn () => [new TopTaxonomyTermCard, 'top_taxonomy_term', Confidence::Partial],
    fn () => [new LongestUntouchedCard, 'longest_untouched', Confidence::Partial],
]);

describe('longest entry', function () {
    it('names the longest thing published', function () {
        page('short', ['body' => 'One two three']);
        page('long', ['body' => str_repeat('word ', 400)]);

        $card = (new LongestEntryCard(new WordCounter))->compute(cardContext([
            wasPublished('short', '2026-02-01 09:00:00'),
            wasPublished('long', '2026-06-01 09:00:00'),
        ]));

        expect($card['id'])->toBe('long')
            ->and($card['title'])->toBe('Long')
            ->and($card['collection'])->toBe('blog')
            ->and($card['words'])->toBe(401);
    });

    it('skips an entry deleted since it was published', function () {
        page('short', ['body' => 'One two three']);

        $card = (new LongestEntryCard(new WordCounter))->compute(cardContext([
            wasPublished('short', '2026-02-01 09:00:00'),
            wasPublished('gone', '2026-06-01 09:00:00'),
        ]));

        expect($card['id'])->toBe('short');
    });

    it('shows nothing when nothing was published', function () {
        expect((new LongestEntryCard(new WordCounter))->compute(cardContext([])))->toBeNull();
    });
});

describe('most revised', function () {
    it('names the page edited most', function () {
        page('busy');
        page('calm');

        $card = (new MostRevisedCard)->compute(cardContext([
            wasUpdated('calm', '2026-02-01 09:00:00'),
            wasUpdated('calm', '2026-02-02 09:00:00'),
            wasUpdated('busy', '2026-03-01 09:00:00'),
            wasUpdated('busy', '2026-03-02 09:00:00'),
            wasUpdated('busy', '2026-03-03 09:00:00'),
        ]));

        expect($card)->toBe(['id' => 'busy', 'title' => 'Busy', 'collection' => 'blog', 'edits' => 3]);
    });

    it('disappears on a source that can only ever report one edit per page', function () {
        page('a');
        page('b');

        expect((new MostRevisedCard)->compute(cardContext([
            wasUpdated('a', '2026-02-01 09:00:00'),
            wasUpdated('b', '2026-03-01 09:00:00'),
        ])))->toBeNull();
    });

    it('does not count publishing as editing', function () {
        page('a');

        expect((new MostRevisedCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00'),
            wasPublished('a', '2026-03-01 09:00:00'),
            wasUpdated('a', '2026-04-01 09:00:00'),
        ])))->toBeNull();
    });

    it('falls to the runner-up when the winner has been deleted', function () {
        page('calm');

        $card = (new MostRevisedCard)->compute(cardContext([
            wasUpdated('gone', '2026-02-01 09:00:00'),
            wasUpdated('gone', '2026-02-02 09:00:00'),
            wasUpdated('gone', '2026-02-03 09:00:00'),
            wasUpdated('calm', '2026-03-01 09:00:00'),
            wasUpdated('calm', '2026-03-02 09:00:00'),
        ]));

        expect($card['id'])->toBe('calm')
            ->and($card['edits'])->toBe(2);
    });
});

describe('fastest turnaround', function () {
    it('measures created to published, in seconds', function () {
        page('quick');
        page('slow');

        $card = (new FastestTurnaroundCard)->compute(cardContext([
            wasCreated('slow', '2026-02-01 09:00:00'),
            wasPublished('slow', '2026-02-03 09:00:00'),
            wasCreated('quick', '2026-03-01 09:00:00'),
            wasPublished('quick', '2026-03-01 09:42:00'),
        ]));

        expect($card['id'])->toBe('quick')
            ->and($card['seconds'])->toBe(2520);
    });

    it('ignores an entry it never saw created', function () {
        page('quick');
        page('unknown');

        $card = (new FastestTurnaroundCard)->compute(cardContext([
            wasPublished('unknown', '2026-01-05 09:00:00'),
            wasCreated('quick', '2026-03-01 09:00:00'),
            wasPublished('quick', '2026-03-01 09:42:00'),
        ]));

        expect($card['id'])->toBe('quick');
    });

    it('ignores a publication recorded before its own creation', function () {
        page('impossible');

        expect((new FastestTurnaroundCard)->compute(cardContext([
            wasPublished('impossible', '2026-03-01 09:00:00'),
            wasCreated('impossible', '2026-03-01 10:00:00'),
        ])))->toBeNull();
    });

    it('shows nothing without creation events', function () {
        page('a');

        expect((new FastestTurnaroundCard)->compute(cardContext([
            wasPublished('a', '2026-03-01 09:00:00'),
        ])))->toBeNull();
    });
});

describe('top taxonomy term', function () {
    it('names the term the period was mostly about', function () {
        page('a', ['topics' => ['design']]);
        page('b', ['topics' => ['design', 'news']]);
        page('c', ['topics' => ['design']]);

        $card = (new TopTaxonomyTermCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00'),
            wasPublished('b', '2026-03-01 09:00:00'),
            wasPublished('c', '2026-04-01 09:00:00'),
        ]));

        expect($card)->toBe(['taxonomy' => 'topics', 'term' => 'design', 'count' => 3]);
    });

    it('counts a term once per entry however often it is listed', function () {
        page('a', ['topics' => ['design', 'design']]);
        page('b', ['topics' => ['design']]);

        $card = (new TopTaxonomyTermCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00'),
            wasPublished('b', '2026-03-01 09:00:00'),
        ]));

        expect($card['count'])->toBe(2);
    });

    it('reads a term stored as taxonomy::slug', function () {
        page('a', ['topics' => ['topics::design']]);
        page('b', ['topics' => ['design']]);

        $card = (new TopTaxonomyTermCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00'),
            wasPublished('b', '2026-03-01 09:00:00'),
        ]));

        expect($card)->toBe(['taxonomy' => 'topics', 'term' => 'design', 'count' => 2]);
    });

    it('shows nothing when no term was used twice', function () {
        page('a', ['topics' => ['design']]);
        page('b', ['topics' => ['news']]);

        expect((new TopTaxonomyTermCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00'),
            wasPublished('b', '2026-03-01 09:00:00'),
        ])))->toBeNull();
    });

    it('shows nothing when nothing is tagged', function () {
        page('a');

        expect((new TopTaxonomyTermCard)->compute(cardContext([
            wasPublished('a', '2026-02-01 09:00:00'),
        ])))->toBeNull();
    });
});

describe('longest untouched', function () {
    it('names the page quietly doing its job', function () {
        page('ancient');
        page('recent');

        $card = (new LongestUntouchedCard)->compute(cardContext([
            wasUpdated('ancient', '2021-06-04 09:00:00'),
            wasUpdated('recent', '2025-11-01 09:00:00'),
            wasUpdated('recent', '2026-05-01 09:00:00'),
        ]));

        expect($card)->toBe([
            'id' => 'ancient',
            'title' => 'Ancient',
            'collection' => 'blog',
            'last_touched' => '2021-06-04',
        ]);
    });

    it('ignores a page touched during the period', function () {
        page('recent');

        expect((new LongestUntouchedCard)->compute(cardContext([
            wasUpdated('recent', '2026-05-01 09:00:00'),
        ])))->toBeNull();
    });

    it('takes an entry\'s most recent touch, not its first', function () {
        page('a');
        page('b');

        $card = (new LongestUntouchedCard)->compute(cardContext([
            wasUpdated('a', '2020-01-01 09:00:00'),
            wasUpdated('a', '2024-01-01 09:00:00'),
            wasUpdated('b', '2022-01-01 09:00:00'),
        ]));

        expect($card['id'])->toBe('b');
    });

    it('skips a page that has since been deleted', function () {
        page('ancient');

        $card = (new LongestUntouchedCard)->compute(cardContext([
            wasUpdated('gone', '2019-01-01 09:00:00'),
            wasUpdated('ancient', '2021-06-04 09:00:00'),
        ]));

        expect($card['id'])->toBe('ancient');
    });

    it('shows nothing on a site with no history at all', function () {
        expect((new LongestUntouchedCard)->compute(cardContext([])))->toBeNull();
    });
});
