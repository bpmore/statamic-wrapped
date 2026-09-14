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
     * @param  list<string>  $frames  PNG bytes, in the order they play.
     * @param  string|null  $audio  Path to an audio file, or null for silence.
     * @return string MP4 bytes.
     *
     * @throws \RuntimeException When the render fails.
     */
    public function render(array $frames, ?string $audio, VideoSpec $spec): string;
}
