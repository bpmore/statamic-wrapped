<?php

namespace Bpmore\Wrapped\Snapshots;

/**
 * What span a snapshot covers.
 *
 * Quarterly is nearly free once yearly works and gives the addon four moments a
 * year instead of one — see SPEC.md §8.
 */
enum Period: string
{
    case Year = 'year';
    case Quarter = 'quarter';

    /**
     * The stored `period_key`: '2026' for a year, '2026-Q3' for a quarter.
     *
     * This is the only place that format is written, because it is a database
     * value that has to stay stable across releases.
     */
    public function key(int $year, ?int $quarter = null): string
    {
        if ($this === self::Year) {
            return (string) $year;
        }

        if ($quarter === null || $quarter < 1 || $quarter > 4) {
            throw new \InvalidArgumentException('A quarterly snapshot needs a quarter between 1 and 4.');
        }

        return sprintf('%d-Q%d', $year, $quarter);
    }
}
