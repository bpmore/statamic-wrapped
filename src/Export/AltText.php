<?php

namespace Bpmore\Wrapped\Export;

use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardPresenter;

/**
 * Suggested alt text, handed over with every image.
 *
 * A card image is text made of pixels: entirely invisible to a screen reader,
 * and there is nothing in it a sighted reader gets that a described reader
 * should not. So the alt text is the card's own words, plus just enough context
 * to say where they came from — SPEC.md §5.
 *
 * Deliberately not "an image of a card showing...". Alt text carries the
 * information, not a description of the medium; a reader wants the fact, not to
 * be told they are missing a picture.
 */
class AltText
{
    public function __construct(protected CardPresenter $presenter) {}

    /**
     * @param  array{handle: string, heading: string, body: string}  $card
     */
    public function forCard(Snapshot $snapshot, array $card): string
    {
        return __('wrapped::messages.alt.card', [
            'heading' => $card['heading'],
            'body' => $card['body'],
            'period' => $snapshot->period_key,
            'site' => $snapshot->site,
        ]);
    }

    /**
     * Every card, in one paragraph. Long, because the image is long, and a
     * truncated description of a summary is worse than none.
     */
    public function forSummary(Snapshot $snapshot): string
    {
        $cards = $this->presenter->present($snapshot->stats);

        $sentences = array_map(
            fn (array $card) => rtrim($card['heading'], ':').': '.$card['body'],
            $cards,
        );

        // Trimmed: an empty Wrapped would otherwise leave the sentence
        // trailing a space where the cards should have been.
        return trim(__('wrapped::messages.alt.summary', [
            'period' => $snapshot->period_key,
            'site' => $snapshot->site,
            'cards' => implode(' ', $sentences),
        ]));
    }
}
