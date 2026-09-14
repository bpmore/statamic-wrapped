<?php

namespace Bpmore\Wrapped\Export;

/**
 * Turns a sequence of still frames and a piece of music into a video.
 *
 * An interface for the same reasons as ImageRenderer: the tests must not shell
 * out to FFmpeg, and a site may want to swap the implementation.
 */
interface VideoRenderer
{
    public function isAvailable(): bool;

    /**
     * @param  list<Frame>  $frames  In the order they play, each with its own time on screen.
     * @param  string|null  $overlay  PNG bytes with transparency, laid over every frame and never moved. Null for none.
     * @param  string|null  $audio  Path to an audio file, or null for silence.
     * @return string MP4 bytes.
     *
     * @throws \RuntimeException When the render fails.
     */
    public function render(array $frames, ?string $overlay, ?string $audio, VideoSpec $spec): string;
}
