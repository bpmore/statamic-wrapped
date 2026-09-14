<?php

namespace Bpmore\Wrapped\Export;

use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Stats\CardPresenter;
use RuntimeException;

/**
 * A Wrapped, as a video.
 *
 * The editor chooses which cards go in. The addon could guess at "the best
 * eight", but the editor knows their audience and the addon does not, and a
 * guess that is wrong is a video somebody has to redo. What the addon does
 * instead is offer a sensible default and check the choice.
 *
 * Checking matters more than it looks: a card that is not on this Wrapped, or
 * one this viewer may not see, cannot be added by editing a URL. The people
 * gate in particular has to hold here as firmly as it does on the screen.
 */
class WrappedVideo
{
    /**
     * How many cards the default selection reaches for. Eight cards plus an
     * intro and outro is about thirty seconds, which is where attention runs
     * out on the platforms this is made for.
     */
    public const DEFAULT_CARDS = 8;

    public function __construct(
        protected VideoRenderer $renderer,
        protected VideoFrames $frames,
        protected CardPresenter $presenter,
        protected CardGate $gate,
        protected Soundtracks $soundtracks,
    ) {}

    public function isAvailable(): bool
    {
        return $this->renderer->isAvailable();
    }

    /**
     * The cards this viewer may put in a video, in screen order, with each
     * marked as ticked or not by default.
     *
     * @return list<array{handle: string, heading: string, body: string, people: bool, selected: bool}>
     */
    public function choices(Snapshot $snapshot): array
    {
        $choices = [];
        $ticked = 0;

        foreach ($this->presenter->present($snapshot->stats) as $card) {
            $people = $this->gate->isAboutPeople($card['handle']);

            // People cards are never ticked by default. A video gets forwarded
            // in a way a screen does not, so putting a person in one should be
            // a decision, not a default.
            $selected = ! $people && $ticked < self::DEFAULT_CARDS;

            if ($selected) {
                $ticked++;
            }

            $choices[] = $card + ['people' => $people, 'selected' => $selected];
        }

        return $choices;
    }

    /**
     * The handles ticked by default, for a caller that wants the fast path.
     *
     * @return list<string>
     */
    public function defaultSelection(Snapshot $snapshot): array
    {
        return array_values(array_map(
            fn (array $choice) => $choice['handle'],
            array_filter($this->choices($snapshot), fn (array $choice) => $choice['selected']),
        ));
    }

    /**
     * How long a video with these cards would run, so the picker can say so
     * as boxes are ticked.
     */
    public function duration(int $cards, ?VideoSpec $spec = null): float
    {
        return ($spec ?? new VideoSpec)->duration($cards + 2);
    }

    /**
     * @param  list<string>  $handles  Cards to include, in the order given.
     * @param  string|null  $track  A soundtrack handle, or null for the default.
     * @return string MP4 bytes.
     *
     * @throws RuntimeException When a handle is not on this Wrapped, not visible to this viewer, or the track is unknown.
     */
    public function render(Snapshot $snapshot, array $handles, ?string $track = null, ?VideoSpec $spec = null): string
    {
        $cards = $this->resolve($snapshot, $handles);
        $soundtrack = $this->soundtrack($track);

        $frames = [$this->frames->intro($snapshot)];

        foreach ($cards as $card) {
            $frames[] = $this->frames->card($snapshot, $card);
        }

        $frames[] = $this->frames->outro($snapshot);

        return $this->renderer->render($frames, $soundtrack?->path, $spec ?? new VideoSpec);
    }

    public function filename(Snapshot $snapshot): string
    {
        return implode('-', array_filter(['wrapped', $snapshot->period_key, $snapshot->site])).'.mp4';
    }

    /**
     * Every requested handle, turned into a presented card, or an exception.
     * Presentation already applies the people gate, so a card the viewer may
     * not see simply is not in the list to be found.
     *
     * @param  list<string>  $handles
     * @return list<array{handle: string, heading: string, body: string}>
     */
    protected function resolve(Snapshot $snapshot, array $handles): array
    {
        if ($handles === []) {
            throw new RuntimeException('A video needs at least one card.');
        }

        $available = [];

        foreach ($this->presenter->present($snapshot->stats) as $card) {
            $available[$card['handle']] = $card;
        }

        $cards = [];

        foreach (array_unique($handles) as $handle) {
            if (! isset($available[$handle])) {
                throw new RuntimeException("This Wrapped has no [{$handle}] card to put in a video.");
            }

            $cards[] = $available[$handle];
        }

        return $cards;
    }

    protected function soundtrack(?string $handle): ?Soundtrack
    {
        if ($handle === null) {
            return $this->soundtracks->default();
        }

        $track = $this->soundtracks->find($handle);

        if ($track === null) {
            throw new RuntimeException("There is no soundtrack called [{$handle}].");
        }

        return $track;
    }
}
