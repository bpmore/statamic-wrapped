<?php

namespace Bpmore\Wrapped\Stats;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs every card against one period, and leaves out the ones the history
 * cannot honestly support.
 *
 * This is where SPEC.md §1's rule is actually enforced: a card is asked what
 * confidence it needs, and if the resolved source cannot offer it the card is
 * **never computed at all**. Not computed and hidden, not computed with a
 * caveat — not computed. There is no code path where a card's numbers exist
 * without the history to back them.
 */
class CardRunner
{
    public function __construct(protected StatCardRegistry $cards) {}

    public function run(StatContext $context): StatsResult
    {
        $stats = [];
        $omitted = [];
        $empty = [];
        $failed = [];

        foreach ($this->cards->all() as $handle => $card) {
            if (! $context->history->supports($card->requires())) {
                $omitted[] = $handle;

                continue;
            }

            try {
                $result = $card->compute($context);
            } catch (Throwable $e) {
                // One awkward entry must not cost someone their whole year.
                // Recorded and logged rather than swallowed, so this shows up
                // as a bug report instead of a mysteriously missing card.
                Log::warning('[wrapped] Stat card failed and was left out.', [
                    'card' => $handle,
                    'exception' => $e,
                ]);

                $failed[] = $handle;

                continue;
            }

            if ($result === null) {
                $empty[] = $handle;

                continue;
            }

            $stats[$handle] = $result;
        }

        return new StatsResult($stats, $omitted, $empty, $failed);
    }
}
