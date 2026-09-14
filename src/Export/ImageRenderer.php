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

    /**
     * Many pages on one PNG, laid out in a grid of $columns, each cell exactly
     * $width by $height. One browser launch instead of one per page, which is
     * where nearly all the time went when a video was rendered a frame at a
     * time. The caller slices the result.
     *
     * @param  list<string>  $htmls  Independent documents, each rendered in its own cell.
     * @return string Raw PNG bytes, at 1x — the cells are already output size.
     */
    public function renderSheet(array $htmls, int $width, int $height, int $columns, bool $transparent = false): string;
}
