<?php

namespace Bpmore\Wrapped\Tests\Fixtures;

use Bpmore\Wrapped\Export\VideoRenderer;
use Bpmore\Wrapped\Export\VideoSpec;
use RuntimeException;

/**
 * Records what it was asked to stitch instead of running FFmpeg.
 */
class FakeVideoRenderer implements VideoRenderer
{
    /** @var list<string> */
    public array $frames = [];

    public ?string $audio = null;

    public ?VideoSpec $spec = null;

    public function __construct(
        protected bool $available = true,
        protected ?string $fails = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function render(array $frames, ?string $audio, VideoSpec $spec): string
    {
        if ($this->fails !== null) {
            throw new RuntimeException($this->fails);
        }

        $this->frames = $frames;
        $this->audio = $audio;
        $this->spec = $spec;

        return 'MP4:'.count($frames).'frames'.($audio === null ? ':silent' : ':'.basename($audio));
    }
}
