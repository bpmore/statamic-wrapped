<?php

namespace Bpmore\Wrapped\Export;

/**
 * One still and how long it stays on screen.
 *
 * Duration travels with the frame rather than living in the spec, because a
 * frame's time on screen depends on how much there is to read on it.
 */
class Frame
{
    public function __construct(
        public readonly string $png,
        public readonly float $seconds,
    ) {}
}
