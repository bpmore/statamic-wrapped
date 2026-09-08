<?php

namespace Bpmore\Wrapped\History;

/**
 * How much a history source can be trusted.
 *
 * Stat cards declare the confidence they need. A card that needs more than the
 * resolved source can offer is omitted rather than guessed — see SPEC.md §1.
 */
enum Confidence: string
{
    /** Real audit history: who changed what, when. */
    case High = 'high';

    /** Timestamps we believe, but no full edit trail. */
    case Partial = 'partial';

    /** Approximate, and easily destroyed by a deploy. */
    case Low = 'low';

    /**
     * Higher is better. Only meaningful for comparison.
     */
    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Partial => 2,
            self::Low => 1,
        };
    }

    /**
     * Is this confidence good enough for something that requires $required?
     */
    public function meets(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }
}
