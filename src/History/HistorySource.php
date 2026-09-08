<?php

namespace Bpmore\Wrapped\History;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A place edit history can be read from.
 *
 * There is one implementation per source named in SPEC.md §1 — Logbook,
 * Revisions, entry data and file mtimes — and the resolver picks the best one
 * that is actually available on this site.
 */
interface HistorySource
{
    /**
     * Stable machine name, stored on the snapshot: 'logbook', 'revisions',
     * 'entry_data' or 'mtime'.
     */
    public function handle(): string;

    /**
     * Human label, shown in the UI so a Wrapped always says where it came from.
     */
    public function name(): string;

    /**
     * Can this source be read on this site right now? A source backed by an
     * addon that is not installed answers false.
     */
    public function isAvailable(): bool;

    /**
     * How much this source can be trusted. Fixed per source, not per site.
     */
    public function confidence(): Confidence;

    /**
     * Events between $from and $to inclusive, in no guaranteed order.
     *
     * @param  string|null  $site  Site handle, or null for every site.
     * @return Collection<int, HistoryEvent>
     */
    public function events(CarbonImmutable $from, CarbonImmutable $to, ?string $site = null): Collection;

    /**
     * The earliest moment this source has any history for, or null when it has
     * none. Used to frame a site that is younger than a year — see SPEC.md §1.
     */
    public function earliestEvent(?string $site = null): ?CarbonImmutable;
}
