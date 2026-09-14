<?php

namespace Bpmore\Wrapped\Snapshots;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * What span a snapshot covers.
 *
 * Yearly is the Wrapped everyone expects. Quarterly and monthly give a busy
 * site more moments — a newsroom publishing forty things a day has a story
 * every month, not just every December. Same cards, same screen, shorter
 * window.
 */
enum Period: string
{
    case Year = 'year';
    case Quarter = 'quarter';
    case Month = 'month';

    /**
     * The stored `period_key`: '2026' for a year, '2026-Q3' for a quarter,
     * '2026-09' for a month.
     *
     * This is the only place that format is written, because it is a database
     * value that has to stay stable across releases.
     *
     * @param  int|null  $part  The quarter (1-4) or month (1-12); ignored for a year.
     */
    public function key(int $year, ?int $part = null): string
    {
        return match ($this) {
            self::Year => (string) $year,
            self::Quarter => sprintf('%d-Q%d', $year, $this->assertPart($part)),
            self::Month => sprintf('%d-%02d', $year, $this->assertPart($part)),
        };
    }

    /**
     * The period's bounds, in the site's own timezone. Inclusive at both ends,
     * which is what every source's `events()` expects.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(int $year, ?int $part = null): array
    {
        $timezone = config('app.timezone');
        $timezone = is_string($timezone) ? $timezone : null;

        return match ($this) {
            self::Year => (function () use ($year, $timezone) {
                $start = CarbonImmutable::createMidnightDate($year, 1, 1, $timezone);

                return [$start->startOfYear(), $start->endOfYear()];
            })(),
            self::Quarter => (function () use ($year, $part, $timezone) {
                $start = CarbonImmutable::createMidnightDate($year, ($this->assertPart($part) - 1) * 3 + 1, 1, $timezone);

                return [$start->startOfQuarter(), $start->endOfQuarter()];
            })(),
            self::Month => (function () use ($year, $part, $timezone) {
                $start = CarbonImmutable::createMidnightDate($year, $this->assertPart($part), 1, $timezone);

                return [$start->startOfMonth(), $start->endOfMonth()];
            })(),
        };
    }

    /**
     * The same window, one period earlier: 2025 for 2026, Q2 for Q3, August
     * for September. Shifted by the period rather than by the window's length,
     * so leap years and uneven months land on the right dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function previous(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($this) {
            self::Year => [$from->subYear(), $to->subYear()],
            self::Quarter => [$from->subMonthsNoOverflow(3), $to->subMonthsNoOverflow(3)],
            // End of month has to be recomputed: 31 August minus a month is
            // not the end of July.
            self::Month => [$from->subMonthsNoOverflow(1)->startOfMonth(), $from->subMonthsNoOverflow(1)->endOfMonth()],
        };
    }

    /**
     * A period key back into something a person reads: "2026", "Q3 2026",
     * "September 2026". Translated month names; the CP locale decides.
     */
    public static function label(string $key): string
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $key, $m)) {
            return __('wrapped::messages.period.quarter', ['quarter' => $m[2], 'year' => $m[1]]);
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            return CarbonImmutable::createMidnightDate((int) $m[1], (int) $m[2], 1)->translatedFormat('F Y');
        }

        return $key;
    }

    /**
     * How many of these there are in a year, for validation and for wording.
     */
    protected function parts(): int
    {
        return match ($this) {
            self::Year => 1,
            self::Quarter => 4,
            self::Month => 12,
        };
    }

    /**
     * A quarterly or monthly period with no usable part is a programming
     * error, not a value to fall back from — it would write a malformed key
     * into the database or silently build the wrong span.
     */
    protected function assertPart(?int $part): int
    {
        if ($part === null || $part < 1 || $part > $this->parts()) {
            throw new InvalidArgumentException(sprintf(
                'A %s snapshot needs a value between 1 and %d.',
                $this->value,
                $this->parts(),
            ));
        }

        return $part;
    }
}
