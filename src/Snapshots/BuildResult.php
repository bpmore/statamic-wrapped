<?php

namespace Bpmore\Wrapped\Snapshots;

use Bpmore\Wrapped\History\ResolvedHistory;
use Bpmore\Wrapped\Stats\StatsResult;

/**
 * What one call to {@see SnapshotBuilder::build()} did for one site.
 *
 * Either a snapshot was written and `stats` says what went into it, or one
 * already existed and was left alone (`skipped`, with the snapshot that was
 * already there). The history is what it was built from either way.
 */
final class BuildResult
{
    public function __construct(
        public readonly Snapshot $snapshot,
        public readonly ResolvedHistory $history,
        public readonly ?StatsResult $stats,
        public readonly bool $skipped,
    ) {}

    public static function built(Snapshot $snapshot, ResolvedHistory $history, StatsResult $stats): self
    {
        return new self($snapshot, $history, $stats, skipped: false);
    }

    public static function skipped(Snapshot $existing, ResolvedHistory $history): self
    {
        return new self($existing, $history, stats: null, skipped: true);
    }
}
