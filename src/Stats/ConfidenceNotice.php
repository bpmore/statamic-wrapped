<?php

namespace Bpmore\Wrapped\Stats;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Snapshot;

/**
 * The line that keeps a Wrapped honest.
 *
 * A Wrapped never presents itself without saying what it was built from, and
 * escalates the wording only as far as the truth requires — see SPEC.md §1.
 *
 * **How the omitted count is worked out.** The snapshot does not store which
 * cards were left out; it does not need to. A card declares the confidence it
 * needs and the snapshot records the confidence it had, so the answer is
 * derivable and stays correct as cards are added. It also answers the more
 * useful question: what would this site get if its history improved.
 */
class ConfidenceNotice
{
    public function __construct(protected StatCardRegistry $cards) {}

    /**
     * @return array{
     *     source: string,
     *     sourceName: string,
     *     confidence: string,
     *     limited: bool,
     *     omitted: int,
     *     notice: string,
     *     suggestion: string|null,
     * }
     */
    public function for(Snapshot $snapshot): array
    {
        $name = $this->sourceName($snapshot->history_source);
        $omitted = $this->omitted($snapshot);
        $limited = ! $snapshot->supports(Confidence::High);

        return [
            'source' => $snapshot->history_source,
            'sourceName' => $name,
            'confidence' => $snapshot->confidence->value,
            'limited' => $limited,
            'omitted' => $omitted,
            'notice' => $this->notice($snapshot, $name, $omitted),
            // Offered once, where it would actually help, and never as a nag on
            // a Wrapped that is already complete.
            'suggestion' => $limited && $snapshot->history_source !== 'logbook'
                ? __('wrapped::messages.notice.suggestion')
                : null,
        ];
    }

    protected function notice(Snapshot $snapshot, string $name, int $omitted): string
    {
        if ($snapshot->stats === []) {
            return __('wrapped::messages.notice.empty', ['source' => $name]);
        }

        if ($omitted === 0) {
            return __('wrapped::messages.notice.complete', ['source' => $name]);
        }

        return trans_choice('wrapped::messages.notice.limited', $omitted, [
            'source' => $name,
            'count' => (string) $omitted,
        ]);
    }

    /**
     * Cards this snapshot's history could never have supported.
     */
    protected function omitted(Snapshot $snapshot): int
    {
        return $this->cards->all()
            ->reject(fn (StatCard $card) => $snapshot->supports($card->requires()))
            ->count();
    }

    /**
     * Named, not handled. "mtime" means nothing to a reader.
     */
    protected function sourceName(string $handle): string
    {
        $key = "wrapped::messages.source.{$handle}";
        $name = __($key);

        return $name === $key ? __('wrapped::messages.source.unknown') : $name;
    }
}
