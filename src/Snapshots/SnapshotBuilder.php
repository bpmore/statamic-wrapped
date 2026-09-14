<?php

namespace Bpmore\Wrapped\Snapshots;

use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\History\ResolvedHistory;
use Bpmore\Wrapped\Stats\CardRunner;
use Bpmore\Wrapped\Stats\StatContext;
use Carbon\CarbonImmutable;

/**
 * Builds a Wrapped for one site and one period, and caches it.
 *
 * Aggregating across forty thousand entries on page load will not do, so this
 * does the work once and writes the answer to `wrapped_snapshots` — SPEC.md §4.
 * The console command and the control panel's Build button both come here;
 * neither knows anything the other does not.
 */
class SnapshotBuilder
{
    public function __construct(
        protected HistorySourceResolver $resolver,
        protected CardRunner $runner,
    ) {}

    /**
     * The history every build reads from, or the reason there can be none.
     *
     * @throws NoReadableHistory
     */
    public function history(): ResolvedHistory
    {
        return $this->resolver->resolve() ?? throw new NoReadableHistory;
    }

    /**
     * Build one snapshot, or leave the existing one alone unless forced.
     *
     * @param  int|null  $part  The quarter (1-4) or month (1-12); null for a year.
     * @param  string|null  $by  Who asked. Null for an unattended run, which is the honest answer for one.
     *
     * @throws NoReadableHistory
     * @throws \InvalidArgumentException For a part the period does not have.
     */
    public function build(Period $period, int $year, ?int $part, string $site, bool $force = false, ?string $by = null): BuildResult
    {
        $history = $this->history();
        $key = $period->key($year, $part);
        [$from, $to] = $period->window($year, $part);

        $existing = Snapshot::query()
            ->where('site', $site)
            ->where('period', $period->value)
            ->where('period_key', $key)
            ->first();

        if ($existing !== null && ! $force) {
            return BuildResult::skipped($existing, $history);
        }

        $result = $this->runner->run(new StatContext(
            history: $history,
            period: $period,
            from: $from,
            to: $to,
            site: $site,
        ));

        $snapshot = Snapshot::query()->updateOrCreate(
            ['site' => $site, 'period' => $period->value, 'period_key' => $key],
            [
                // Stored per snapshot, not derived on read: this is what the
                // numbers were built from, whatever is installed later.
                'history_source' => $history->handle(),
                'confidence' => $history->confidence,
                'stats' => $result->stats,
                'started_at' => $this->startedAt($site, $from),
                'generated_at' => CarbonImmutable::now(),
                'generated_by' => $by,
            ],
        );

        return BuildResult::built($snapshot, $history, $result);
    }

    /**
     * When the site began, if that was inside the period being wrapped.
     *
     * A site three months old has no "2026"; it has "since June". Null for a
     * site older than the period, which is the usual case and needs no framing.
     */
    protected function startedAt(string $site, CarbonImmutable $from): ?CarbonImmutable
    {
        $earliest = $this->resolver->earliestKnown($site);

        return $earliest !== null && $earliest->greaterThan($from) ? $earliest : null;
    }
}
