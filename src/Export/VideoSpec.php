<?php

namespace Bpmore\Wrapped\Export;

/**
 * The shape of a video, separate from the frames that go in it.
 *
 * Kept as plain numbers so a renderer can be swapped and so the tests can say
 * "thirty seconds" without knowing how FFmpeg spells it.
 */
class VideoSpec
{
    public function __construct(
        public readonly int $width = VideoFrames::WIDTH,
        public readonly int $height = VideoFrames::HEIGHT,
        /** How long each frame stays on screen, including its fade in. */
        public readonly float $secondsPerFrame = 3.0,
        /** How long the crossfade between frames lasts. */
        public readonly float $crossfade = 0.6,
        public readonly int $fps = 30,
    ) {}

    /**
     * Total running time. Frames overlap by the crossfade, so n frames run for
     * n * seconds minus (n - 1) crossfades.
     */
    public function duration(int $frames): float
    {
        if ($frames === 0) {
            return 0.0;
        }

        return $frames * $this->secondsPerFrame - ($frames - 1) * $this->crossfade;
    }
}
