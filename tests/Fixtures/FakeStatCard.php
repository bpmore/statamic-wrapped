<?php

namespace Bpmore\Wrapped\Tests\Fixtures;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;

/**
 * A card with no real computation, so the registry and the context can be
 * tested before any real card exists.
 */
class FakeStatCard implements StatCard
{
    public int $computed = 0;

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(
        protected string $handle,
        protected Confidence $requires = Confidence::Low,
        protected ?array $result = ['ok' => true],
        protected ?string $throws = null,
    ) {}

    public function handle(): string
    {
        return $this->handle;
    }

    public function requires(): Confidence
    {
        return $this->requires;
    }

    public function compute(StatContext $context): ?array
    {
        $this->computed++;

        if ($this->throws !== null) {
            throw new \RuntimeException($this->throws);
        }

        return $this->result;
    }
}
