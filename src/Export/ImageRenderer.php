<?php

namespace Bpmore\Wrapped\Export;

/**
 * Turns a piece of HTML into a PNG.
 *
 * An interface rather than one class because the tests must not shell out to a
 * browser, and because a site running somewhere odd may want to swap in its own.
 */
interface ImageRenderer
{
    /**
     * Can this renderer actually run here? A missing browser is a reason to
     * hide the download button, not to fail a request.
     */
    public function isAvailable(): bool;

    /**
     * @param  bool  $transparent  Leave the page background see-through, for something laid over other frames.
     * @return string Raw PNG bytes.
     *
     * @throws \RuntimeException When the render fails.
     */
    public function render(string $html, int $width, int $height, bool $transparent = false): string;
}
