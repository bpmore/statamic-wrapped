<?php

namespace Bpmore\Wrapped\Export;

/**
 * The shape of a video, separate from the frames that go in it.
 *
 * The timing here is what makes the video meet WCAG 2.1 AA for text that
 * advances on its own. There is no fixed number in the guidelines; the
 * principle (2.2.1, 2.2.2) is that a reader must have enough time. Reading
 * speed for on-screen text drops well below prose speed — people read a
 * phone in a feed, glance away, come back — so the budget is generous:
 * a floor for the shortest card and a per-word allowance on top.
 */
class VideoSpec
{
    public function __construct(
        public readonly int $width = VideoFrames::WIDTH,
        public readonly int $height = VideoFrames::HEIGHT,
        /** No card is shown for less than this, however short its text. */
        public readonly float $minimumSeconds = 4.0,
        /** Added per word of on-screen text. Twelve words is about 6.3 seconds. */
        public readonly float $secondsPerWord = 0.4,
        /** Fixed time before the per-word allowance starts. */
        public readonly float $baseSeconds = 1.5,
        /** No card is shown for longer than this either; a wall of text is a design bug, not a reason for a ten-second frame. */
        public readonly float $maximumSeconds = 9.0,
        /** How long the crossfade between frames lasts. Gentle, not snappy. */
        public readonly float $crossfade = 0.8,
        public readonly int $fps = 30,
    ) {}

    /**
     * How long a frame carrying this text should stay up.
     */
    public function secondsFor(string $text): float
    {
        $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $seconds = $this->baseSeconds + $words * $this->secondsPerWord;

        return round(min(max($seconds, $this->minimumSeconds), $this->maximumSeconds), 2);
    }

    /**
     * Total running time for these frames. They overlap by the crossfade, so
     * the sum of their times minus one crossfade per join.
     *
     * @param  list<Frame>|list<float>  $frames  Frames, or just their durations.
     */
    public function duration(array $frames): float
    {
        if ($frames === []) {
            return 0.0;
        }

        $seconds = array_map(fn ($frame) => $frame instanceof Frame ? $frame->seconds : (float) $frame, $frames);

        return round(array_sum($seconds) - (count($seconds) - 1) * $this->crossfade, 2);
    }
}
