<?php

namespace Bpmore\Wrapped\Console\Commands;

use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\History\ResolvedHistory;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardRunner;
use Bpmore\Wrapped\Stats\StatContext;
use Bpmore\Wrapped\Stats\StatsResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Statamic\Facades\Site;
use Statamic\Facades\User;

/**
 * Builds a Wrapped and caches it.
 *
 * Aggregating across forty thousand entries on page load will not do, so this
 * does the work once and writes the answer to `wrapped_snapshots` — SPEC.md §4.
 */
class GenerateWrapped extends Command
{
    protected HistorySourceResolver $resolver;

    protected $signature = 'wrapped:generate
        {--year= : The year to build. Defaults to the current year.}
        {--quarter= : Build a single quarter (1-4) instead of the whole year.}
        {--month= : Build a single month (1-12) instead of the whole year.}
        {--site= : Build one site. Defaults to every site.}
        {--force : Rebuild a snapshot that already exists.}';

    protected $description = 'Build a Wrapped for a year, a quarter or a month.';

    public function handle(HistorySourceResolver $resolver, CardRunner $runner): int
    {
        try {
            [$period, $year, $quarter] = $this->period();
            $sites = $this->sites();
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        }

        $resolved = $resolver->resolve();

        if ($resolved === null) {
            $this->components->error('There is no readable history on this site, so there is nothing to build.');

            return self::FAILURE;
        }

        [$from, $to] = $period->window($year, $quarter);
        $key = $period->key($year, $quarter);
        $this->resolver = $resolver;

        $this->components->info(sprintf(
            'Building %s from %s (%s confidence).',
            $key,
            $resolved->name(),
            $resolved->confidence->value,
        ));

        foreach ($sites as $site) {
            $this->generate($runner, $resolved, $period, $key, $from, $to, $site);
        }

        return self::SUCCESS;
    }

    protected function generate(
        CardRunner $runner,
        ResolvedHistory $resolved,
        Period $period,
        string $key,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $site,
    ): void {
        $existing = Snapshot::query()
            ->where('site', $site)
            ->where('period', $period->value)
            ->where('period_key', $key)
            ->first();

        if ($existing !== null && ! $this->option('force')) {
            $this->components->twoColumnDetail($site, '<fg=yellow>already built, --force to rebuild</>');

            return;
        }

        $result = $runner->run(new StatContext(
            history: $resolved,
            period: $period,
            from: $from,
            to: $to,
            site: $site,
        ));

        Snapshot::query()->updateOrCreate(
            ['site' => $site, 'period' => $period->value, 'period_key' => $key],
            [
                // Stored per snapshot, not derived on read: this is what the
                // numbers were built from, whatever is installed later.
                'history_source' => $resolved->handle(),
                'confidence' => $resolved->confidence,
                'stats' => $result->stats,
                'started_at' => $this->startedAt($site, $from),
                'generated_at' => CarbonImmutable::now(),
                'generated_by' => $this->actor(),
            ],
        );

        $this->components->twoColumnDetail($site, $this->summarise($result));
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

    protected function summarise(StatsResult $result): string
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
        $year = $year === null ? (int) CarbonImmutable::now()->year : (int) $year;

        if ($year < 1970 || $year > 9999) {
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
            if (! is_numeric($quarter) || (int) $quarter < 1 || (int) $quarter > 4) {
                throw new InvalidArgumentException("[{$quarter}] is not a quarter. Use 1, 2, 3 or 4.");
            }

            return [Period::Quarter, $year, (int) $quarter];
        }

        if (! is_numeric($month) || (int) $month < 1 || (int) $month > 12) {
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
