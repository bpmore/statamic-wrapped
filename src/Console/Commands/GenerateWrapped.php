<?php

namespace Bpmore\Wrapped\Console\Commands;

use Bpmore\Wrapped\Snapshots\BuildResult;
use Bpmore\Wrapped\Snapshots\NoReadableHistory;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\SnapshotBuilder;
use Bpmore\Wrapped\Stats\StatsResult;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Builds a Wrapped and caches it, from the terminal or a schedule.
 *
 * The work is {@see SnapshotBuilder}'s, shared with the control panel's Build
 * button; this is the options, the sites, and what gets printed.
 */
class GenerateWrapped extends Command
{
    protected $signature = 'wrapped:generate
        {--year= : The year to build. Defaults to the current year.}
        {--quarter= : Build a single quarter (1-4) instead of the whole year.}
        {--month= : Build a single month (1-12) instead of the whole year.}
        {--site= : Build one site. Defaults to every site.}
        {--force : Rebuild a snapshot that already exists.}';

    protected $description = 'Build a Wrapped for a year, a quarter or a month.';

    public function handle(SnapshotBuilder $builder): int
    {
        try {
            [$period, $year, $part] = $this->period();
            $sites = $this->sites();
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        try {
            $history = $builder->history();
        } catch (NoReadableHistory $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Building %s from %s (%s confidence).',
            $period->key($year, $part),
            $history->name(),
            $history->confidence->value,
        ));

        foreach ($sites as $site) {
            $result = $builder->build($period, $year, $part, $site, force: (bool) $this->option('force'), by: $this->actor());

            $this->components->twoColumnDetail($site, $this->summarize($result));
        }

        return self::SUCCESS;
    }

    /**
     * Who asked for this, when anybody did. A scheduled run has no user, and a
     * null `generated_by` is the honest answer for one.
     *
     * `getAuthIdentifier()` rather than `id()`: Statamic's user contract does
     * not declare `id()`, it inherits the identifier from Authenticatable.
     */
    protected function actor(): ?string
    {
        $id = User::current()?->getAuthIdentifier();

        return $id === null ? null : (string) $id;
    }

    protected function summarize(BuildResult $result): string
    {
        if ($result->skipped || $result->stats === null) {
            return '<fg=yellow>already built, --force to rebuild</>';
        }

        return $this->summarizeStats($result->stats);
    }

    protected function summarizeStats(StatsResult $result): string
    {
        $parts = [sprintf('%d cards', count($result->stats))];

        if ($result->omitted !== []) {
            $parts[] = sprintf('<fg=yellow>%d left out (history too limited)</>', count($result->omitted));
        }

        if ($result->empty !== []) {
            $parts[] = sprintf('%d with nothing to say', count($result->empty));
        }

        if ($result->failed !== []) {
            $parts[] = sprintf('<fg=red>%d failed</>', count($result->failed));
        }

        return implode(', ', $parts);
    }

    /**
     * @return array{0: Period, 1: int, 2: int|null}
     */
    protected function period(): array
    {
        $year = $this->option('year');
        $year = $year === null ? Period::currentYear() : (int) $year;

        if (! Period::isYear($year)) {
            throw new InvalidArgumentException("[{$year}] is not a year this can build.");
        }

        $quarter = $this->option('quarter');
        $month = $this->option('month');

        if ($quarter !== null && $month !== null) {
            throw new InvalidArgumentException('Build a quarter or a month, not both.');
        }

        if ($quarter === null && $month === null) {
            return [Period::Year, $year, null];
        }

        if ($quarter !== null) {
            if (! is_numeric($quarter) || ! Period::Quarter->hasPart((int) $quarter)) {
                throw new InvalidArgumentException("[{$quarter}] is not a quarter. Use 1, 2, 3 or 4.");
            }

            return [Period::Quarter, $year, (int) $quarter];
        }

        if (! is_numeric($month) || ! Period::Month->hasPart((int) $month)) {
            throw new InvalidArgumentException("[{$month}] is not a month. Use 1 to 12.");
        }

        return [Period::Month, $year, (int) $month];
    }

    /**
     * Every site gets its own Wrapped, which is how everything else in this
     * line is scoped — SPEC.md §8.
     *
     * @return list<string>
     */
    protected function sites(): array
    {
        $handles = Site::all()->map->handle()->values()->all();

        if (($only = $this->option('site')) === null) {
            return $handles;
        }

        if (! in_array($only, $handles, true)) {
            throw new InvalidArgumentException("There is no site called [{$only}].");
        }

        return [$only];
    }
}
