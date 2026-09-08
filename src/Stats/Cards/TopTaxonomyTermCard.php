<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\Concerns\DescribesEntries;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Fields\Field;
use Throwable;

/**
 * What this period was mostly about.
 *
 * Read from the entries published in the period, through their blueprints:
 * every field of type `terms` is a taxonomy field, and its `taxonomies` config
 * says which taxonomy a bare slug belongs to. Statamic stores bare slugs when
 * a field points at one taxonomy and `taxonomy::slug` when it points at
 * several, so both shapes are handled.
 *
 * The taxonomy handle and the term slug are stored, never the term's title.
 * Titles are localised and can be edited; the CP screen resolves them at
 * display time.
 *
 * A term used once is not what a period was "mostly about", so the card needs
 * at least two.
 */
class TopTaxonomyTermCard implements StatCard
{
    use DescribesEntries, ReadsPublishedEntries;

    protected const FEWEST_WORTH_SHOWING = 2;

    public function handle(): string
    {
        return 'top_taxonomy_term';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $counts = [];

        foreach ($this->firstPublications($context->events()) as $event) {
            $entry = $this->findEntry($event->itemId);

            if ($entry === null) {
                continue;
            }

            foreach ($this->termsOn($entry) as $term) {
                $counts[$term] = ($counts[$term] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        $term = (string) array_key_first($counts);
        $count = $counts[$term];

        if ($count < self::FEWEST_WORTH_SHOWING) {
            return null;
        }

        [$taxonomy, $slug] = explode('::', $term, 2);

        return ['taxonomy' => $taxonomy, 'term' => $slug, 'count' => $count];
    }

    /**
     * Every term on an entry, as `taxonomy::slug`.
     *
     * @return list<string>
     */
    protected function termsOn(EntryModel $entry): array
    {
        try {
            $fields = $entry->blueprint()->fields()->all();
        } catch (Throwable) {
            return [];
        }

        $terms = [];

        foreach ($fields as $field) {
            if ($field->type() !== 'terms') {
                continue;
            }

            foreach ($this->valuesOf($entry->get($field->handle())) as $value) {
                $term = $this->qualify($value, $field);

                if ($term !== null) {
                    $terms[] = $term;
                }
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * A terms field holds one slug or many, depending on its config.
     *
     * @return list<string>
     */
    protected function valuesOf(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return (new Collection($value))
            ->map(fn ($item) => $this->string($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * `taxonomy::slug` when the value already says so, otherwise the field's
     * single taxonomy. A bare slug on a multi-taxonomy field is ambiguous and
     * is dropped rather than attributed to the wrong taxonomy.
     */
    protected function qualify(string $value, Field $field): ?string
    {
        if (str_contains($value, '::')) {
            return $value;
        }

        $taxonomies = $field->get('taxonomies', []);
        $taxonomies = is_array($taxonomies) ? array_values($taxonomies) : [$taxonomies];

        if (count($taxonomies) !== 1) {
            return null;
        }

        $taxonomy = $this->string($taxonomies[0]);

        return $taxonomy === null ? null : $taxonomy.'::'.$value;
    }
}
