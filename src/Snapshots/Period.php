<?php

namespace Bpmore\Wrapped\Snapshots;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

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

        return sprintf('%d-Q%d', $year, $this->assertQuarter($quarter));
    }

    /**
     * The period's bounds, in the site's own timezone. Inclusive at both ends,
     * which is what every source's `events()` expects.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(int $year, ?int $quarter = null): array
    {
        $timezone = config('app.timezone');
        $timezone = is_string($timezone) ? $timezone : null;

        if ($this === self::Year) {
            $start = CarbonImmutable::createMidnightDate($year, 1, 1, $timezone);

            return [$start->startOfYear(), $start->endOfYear()];
        }

        $month = ($this->assertQuarter($quarter) - 1) * 3 + 1;
        $start = CarbonImmutable::createMidnightDate($year, $month, 1, $timezone);

        return [$start->startOfQuarter(), $start->endOfQuarter()];
    }

    /**
     * A quarterly period with no usable quarter is a programming error, not a
     * value to fall back from — it would write a malformed key into the
     * database or silently build the wrong three months.
     */
    protected function assertQuarter(?int $quarter): int
    {
        if ($quarter === null || $quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('A quarterly snapshot needs a quarter between 1 and 4.');
        }

        return $quarter;
    }
}
