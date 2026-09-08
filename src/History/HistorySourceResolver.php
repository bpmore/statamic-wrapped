<?php

namespace Bpmore\Wrapped\History;

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
     * The confidence of the resolved source. Low when nothing is available at
     * all, because that is the honest answer rather than an error.
     */
    public function confidence(): Confidence
    {
        $resolved = $this->resolve();

        return $resolved === null ? Confidence::Low : $resolved->confidence;
    }
}
