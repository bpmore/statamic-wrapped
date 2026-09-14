<?php

namespace Bpmore\Wrapped\Tests\Fixtures;

use Bpmore\Wrapped\Export\ImageRenderer;
use RuntimeException;

/**
 * Records the HTML it was given instead of shelling out to a browser.
 */
class FakeImageRenderer implements ImageRenderer
{
    public ?string $html = null;

    public ?int $width = null;

    public ?int $height = null;

    public function __construct(
        protected bool $available = true,
        protected ?string $fails = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /** Every render, in order, so a multi-frame caller can be checked. */
    public array $renders = [];

    public ?bool $transparent = null;

    public function render(string $html, int $width, int $height, bool $transparent = false): string
    {
        if ($this->fails !== null) {
            throw new RuntimeException($this->fails);
        }

        $this->html = $html;
        $this->width = $width;
        $this->height = $height;
        $this->transparent = $transparent;
        $this->renders[] = ['html' => $html, 'transparent' => $transparent];

        return "PNG:{$width}x{$height}".($transparent ? ':transparent' : '');
    }
}
