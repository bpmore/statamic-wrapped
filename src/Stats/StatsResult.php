<?php

namespace Bpmore\Wrapped\Stats;

/**
 * What came back from running every card.
 *
 * The three lists alongside the stats are not diagnostics for their own sake.
 * "Left out because your history cannot support it" and "computed, and there
 * was no story" look identical on a finished Wrapped — one silently shrinks the
 * product and the other is just a quiet year — and the CP screen has to be able
 * to tell a reader which happened. See SPEC.md §1.
 */
class StatsResult
{
    /**
     * @param  array<string, array<string, mixed>>  $stats  Card handle => its data.
     * @param  list<string>  $omitted  Never computed: the resolved history is not good enough.
     * @param  list<string>  $empty  Computed, and there was nothing worth showing.
     * @param  list<string>  $failed  Threw. Logged, and the rest of the Wrapped carried on.
     */
    public function __construct(
        public readonly array $stats = [],
        public readonly array $omitted = [],
        public readonly array $empty = [],
        public readonly array $failed = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->stats === [];
    }

    /**
     * Did anything get left out for want of better history? This is what the
     * "based on limited history data" notice hangs on.
     */
    public function wasLimited(): bool
    {
        return $this->omitted !== [];
    }
}
