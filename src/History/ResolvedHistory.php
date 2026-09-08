<?php

namespace Bpmore\Wrapped\History;

/**
 * The source the resolver settled on, plus the confidence that comes with it.
 *
 * These two values are written straight onto the snapshot row and shown in the
 * UI, so a Wrapped can always say what it was built from.
 */
class ResolvedHistory
{
    public function __construct(
        public readonly HistorySource $source,
        public readonly Confidence $confidence,
    ) {}

    public static function for(HistorySource $source): self
    {
        return new self($source, $source->confidence());
    }

    public function handle(): string
    {
        return $this->source->handle();
    }

    public function name(): string
    {
        return $this->source->name();
    }

    /**
     * Is this good enough for a card that needs $required? Cards that answer
     * false are omitted, not guessed.
     */
    public function supports(Confidence $required): bool
    {
        return $this->confidence->meets($required);
    }
}
