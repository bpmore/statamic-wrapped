<?php

namespace Bpmore\Wrapped\History;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Picks the best history source this site actually has.
 *
 * Sources are registered best-first. The resolver takes the highest confidence
 * among the available ones, and on a tie keeps the one registered first.
 */
class HistorySourceResolver
{
    /** @var list<HistorySource> */
    protected array $sources = [];

    protected ?ResolvedHistory $resolved = null;

    /**
     * @param  iterable<HistorySource>  $sources
     */
    public function __construct(iterable $sources = [])
    {
        foreach ($sources as $source) {
            $this->register($source);
        }
    }

    public function register(HistorySource $source): static
    {
        $this->sources[] = $source;
        $this->resolved = null;

        return $this;
    }

    /**
     * Every registered source, available or not.
     *
     * @return Collection<int, HistorySource>
     */
    public function sources(): Collection
    {
        return new Collection($this->sources);
    }

    /**
     * The sources that can be read on this site.
     *
     * @return Collection<int, HistorySource>
     */
    public function available(): Collection
    {
        return $this->sources()->filter(fn (HistorySource $source) => $source->isAvailable())->values();
    }

    /**
     * The best available source, or null when the site has no history at all.
     */
    public function resolve(): ?ResolvedHistory
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $best = null;

        foreach ($this->available() as $source) {
            if ($best === null || $source->confidence()->rank() > $best->confidence()->rank()) {
                $best = $source;
            }
        }

        return $this->resolved = $best === null ? null : ResolvedHistory::for($best);
    }

    /**
     * The oldest moment any trustworthy source has a record of, for the
     * first-year framing in SPEC.md §1: a site that began in June should say
     * "since June", not "2026".
     *
     * Two things make this honest rather than merely available:
     *
     * - It looks across **every** available source, not just the resolved one.
     *   A site that installed Logbook in June but has entries dated back to
     *   2019 is an old site with recent history, not a young site — and the
     *   entry dates are what say so.
     * - It **ignores Low-confidence sources**. File mtimes are always
     *   available and on a deployed site their earliest is the last deploy,
     *   which would make every production site look three weeks old.
     */
    public function earliestKnown(?string $site = null): ?CarbonImmutable
    {
        $earliest = null;

        foreach ($this->available() as $source) {
            if (! $source->confidence()->meets(Confidence::Partial)) {
                continue;
            }

            $candidate = $source->earliestEvent($site);

            if ($candidate !== null && ($earliest === null || $candidate->lessThan($earliest))) {
                $earliest = $candidate;
            }
        }

        return $earliest;
    }

    /**
     * The confidence of the resolved source. Low when nothing is available at
     * all, because that is the honest answer rather than an error.
     */
    public function confidence(): Confidence
    {
        $resolved = $this->resolve();

        return $resolved === null ? Confidence::Low : $resolved->confidence;
    }
}
