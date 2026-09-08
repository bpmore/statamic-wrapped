<?php

namespace Bpmore\Wrapped\Stats\Support;

use Statamic\Entries\Entry as EntryModel;
use Throwable;

/**
 * Roughly how many words an entry contains.
 *
 * "Roughly" is deliberate and unavoidable. There is no single place a Statamic
 * entry keeps its prose: it is spread across text, textarea, markdown and Bard
 * fields, nested inside replicators and grids, and mixed in with values that
 * are not prose at all. Counting is therefore a heuristic, and this class is
 * where the heuristic lives so it can be argued with in one place.
 *
 * Two rules keep it honest:
 *
 * 1. **Only fields the blueprint says are prose are counted.** A select field's
 *    value, a taxonomy handle and an asset path are not words someone wrote.
 * 2. **Inside those fields, structure is skipped and text is kept.** Bard stores
 *    ProseMirror nodes, so `type` and `id` keys are scaffolding while `text`
 *    and `values` are the writing.
 *
 * If `readability-core` ever exists, replace this rather than keep both — see
 * SPEC.md §8.
 */
class WordCounter
{
    /**
     * Fieldtypes that can hold prose. Everything else is skipped outright.
     */
    protected const PROSE_FIELDTYPES = [
        'text',
        'textarea',
        'markdown',
        'bard',
        'replicator',
        'grid',
        'group',
    ];

    /**
     * Keys that carry structure rather than writing. Bard's ProseMirror nodes
     * are the reason this list exists: every node has a `type`, and counting
     * "paragraph" as a word would inflate a long article badly.
     */
    protected const STRUCTURAL_KEYS = [
        'type',
        'id',
        'src',
        'href',
        'url',
        'target',
        'rel',
        'level',
        'enabled',
        'class',
        'style',
    ];

    public function forEntry(EntryModel $entry): int
    {
        try {
            $fields = $entry->blueprint()->fields()->all();
        } catch (Throwable) {
            // An entry whose blueprint cannot be resolved contributes nothing
            // rather than failing the whole Wrapped.
            return 0;
        }

        $words = 0;

        foreach ($fields as $field) {
            if (! in_array($field->type(), self::PROSE_FIELDTYPES, true)) {
                continue;
            }

            $words += $this->count($entry->get($field->handle()));
        }

        return $words;
    }

    /**
     * Words in an arbitrarily nested value.
     */
    public function count(mixed $value): int
    {
        if (is_string($value)) {
            return $this->countString($value);
        }

        if (! is_array($value)) {
            return 0;
        }

        $words = 0;

        foreach ($value as $key => $nested) {
            if (is_string($key) && in_array($key, self::STRUCTURAL_KEYS, true)) {
                continue;
            }

            $words += $this->count($nested);
        }

        return $words;
    }

    protected function countString(string $value): int
    {
        // Bard can be saved as HTML, and markdown carries its own punctuation.
        // Neither should be counted as writing.
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $value = preg_replace('/[#*_>`~\[\]()|-]+/u', ' ', $value) ?? $value;

        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 0 : count($words);
    }
}
