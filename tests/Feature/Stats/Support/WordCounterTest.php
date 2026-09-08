<?php

use Bpmore\Wrapped\Stats\Support\WordCounter;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;

function counter(): WordCounter
{
    return new WordCounter;
}

/**
 * A Bard value as Statamic stores it: ProseMirror nodes, where `type` is
 * scaffolding and `text` is the writing.
 *
 * @return list<array<string, mixed>>
 */
function bard(string ...$paragraphs): array
{
    return array_map(fn (string $text) => [
        'type' => 'paragraph',
        'content' => [['type' => 'text', 'text' => $text]],
    ], $paragraphs);
}

it('counts the words in a plain string', function () {
    expect(counter()->count('One two three four'))->toBe(4);
});

it('is not confused by extra whitespace', function () {
    expect(counter()->count("  One   two \n\n three  "))->toBe(3);
});

it('counts nothing in an empty or non-text value', function (mixed $value) {
    expect(counter()->count($value))->toBe(0);
})->with([[''], ['   '], [null], [42], [true], [[]]]);

it('ignores html tags rather than counting them as words', function () {
    expect(counter()->count('<p>One <strong>two</strong> three</p>'))->toBe(3);
});

it('ignores markdown punctuation', function () {
    expect(counter()->count('## One two'."\n\n".'- three'."\n".'- four'))->toBe(4);
});

it('reads bard nodes without counting their structure', function () {
    expect(counter()->count(bard('One two three', 'four five')))->toBe(5);
});

it('reads the values inside a bard set', function () {
    $value = [[
        'type' => 'set',
        'attrs' => ['id' => 'abc123', 'values' => ['type' => 'quote', 'text' => 'One two three']],
    ]];

    expect(counter()->count($value))->toBe(3);
});

it('reads nested replicator and grid values', function () {
    $value = [
        ['id' => 'a', 'type' => 'columns', 'left' => 'One two', 'right' => 'three'],
        ['id' => 'b', 'type' => 'quote', 'text' => 'four five six'],
    ];

    expect(counter()->count($value))->toBe(6);
});

describe('over a real entry', function () {
    beforeEach(function () {
        $this->content = sys_get_temp_dir().'/wrapped-counter-'.bin2hex(random_bytes(6));

        mkdir($this->content.'/collections', 0777, true);

        Stache::store('collections')->directory($this->content.'/collections');
        Stache::store('entries')->directory($this->content.'/collections');
        Stache::clear();

        Collection::make('blog')->dated(false)->sites(['default'])->save();

        Blueprint::make('post')->setNamespace('collections.blog')->setContents([
            'fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
                ['handle' => 'intro', 'field' => ['type' => 'textarea']],
                ['handle' => 'body', 'field' => ['type' => 'bard']],
                ['handle' => 'topics', 'field' => ['type' => 'taxonomy']],
                ['handle' => 'hero', 'field' => ['type' => 'assets']],
                ['handle' => 'tone', 'field' => ['type' => 'select']],
            ],
        ])->save();
    });

    afterEach(function () {
        exec('rm -rf '.escapeshellarg($this->content));
    });

    function post(array $data): Statamic\Entries\Entry
    {
        Entry::make()
            ->collection('blog')
            ->id('post-1')
            ->slug('post-1')
            ->locale('default')
            ->published(true)
            ->data($data)
            ->save();

        Stache::clear();

        return Entry::find('post-1');
    }

    it('adds up every prose field on the entry', function () {
        $entry = post([
            'title' => 'One two',
            'intro' => 'three four five',
            'body' => bard('six seven'),
        ]);

        expect(counter()->forEntry($entry))->toBe(7);
    });

    it('does not count fields that are not prose', function () {
        $entry = post([
            'title' => 'One two',
            'topics' => ['news', 'features', 'long-reads'],
            'hero' => ['img/one.jpg', 'img/two.jpg'],
            'tone' => 'serious',
        ]);

        expect(counter()->forEntry($entry))->toBe(2);
    });

    it('counts nothing for an entry with no prose in it', function () {
        expect(counter()->forEntry(post(['tone' => 'serious'])))->toBe(0);
    });
});
