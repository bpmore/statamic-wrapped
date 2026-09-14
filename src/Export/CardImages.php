<?php

namespace Bpmore\Wrapped\Export;

use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardPresenter;
use RuntimeException;

/**
 * A Wrapped, as pictures.
 *
 * Where the marketing value lives (SPEC.md §5) — and a convenience, never the
 * artefact. Images are invisible to screen readers, so the control panel screen
 * stays the canonical version and this only makes it shareable. An
 * accessibility-minded addon shipping image-only output is the screenshot
 * nobody wants.
 *
 * Nothing is written to a public directory and no URL is minted. A shareable
 * link leaking a team's internal publishing stats is a support problem and a
 * privacy problem for a free addon: these are downloads, shared deliberately.
 */
class CardImages
{
    public const CARD = [1200, 630];

    public const SUMMARY = [1200, 1500];

    public function __construct(
        protected ImageRenderer $renderer,
        protected CardPresenter $presenter,
        protected Theme $theme,
    ) {}

    public function isAvailable(): bool
    {
        return $this->renderer->isAvailable();
    }

    /**
     * Which cards can be exported, in the order they appear on screen.
     *
     * @return list<string>
     */
    public function exportable(Snapshot $snapshot): array
    {
        return array_column($this->presenter->present($snapshot->stats), 'handle');
    }

    /**
     * @return string Raw PNG bytes.
     */
    public function card(Snapshot $snapshot, string $handle): string
    {
        $card = $this->find($snapshot, $handle);

        if ($card === null) {
            throw new RuntimeException("This Wrapped has no [{$handle}] card to export.");
        }

        [$width, $height] = self::CARD;

        return $this->renderer->render(view('wrapped::export.card', [
            'theme' => $this->theme,
            'heading' => $card['heading'],
            'body' => $card['body'],
            'period' => Period::label($snapshot->period_key),
            'site' => $snapshot->site,
            'width' => $width,
            'height' => $height,
        ])->render(), $width, $height);
    }

    /**
     * @return string Raw PNG bytes.
     */
    public function summary(Snapshot $snapshot): string
    {
        $cards = $this->presenter->present($snapshot->stats);

        if ($cards === []) {
            throw new RuntimeException('This Wrapped has nothing on it to export.');
        }

        [$width, $height] = self::SUMMARY;

        return $this->renderer->render(view('wrapped::export.summary', [
            'theme' => $this->theme,
            'cards' => $cards,
            'period' => Period::label($snapshot->period_key),
            'site' => $snapshot->site,
            'width' => $width,
            'height' => $height,
        ])->render(), $width, $height);
    }

    /**
     * A filename someone can find again in their downloads folder.
     */
    public function filename(Snapshot $snapshot, ?string $handle = null): string
    {
        return implode('-', array_filter(['wrapped', $snapshot->period_key, $snapshot->site, $handle])).'.png';
    }

    /**
     * @return array{handle: string, heading: string, body: string}|null
     */
    protected function find(Snapshot $snapshot, string $handle): ?array
    {
        foreach ($this->presenter->present($snapshot->stats) as $card) {
            if ($card['handle'] === $handle) {
                return $card;
            }
        }

        return null;
    }
}
