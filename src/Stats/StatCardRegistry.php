<?php

namespace Bpmore\Wrapped\Stats;

use Illuminate\Support\Collection;

/**
 * Every card that could appear on a Wrapped.
 *
 * Registration order is the order cards are computed, and is not the order they
 * are shown — that is the CP screen's business.
 */
class StatCardRegistry
{
    /** @var array<string, StatCard> */
    protected array $cards = [];

    /**
     * @param  iterable<StatCard>  $cards
     */
    public function __construct(iterable $cards = [])
    {
        foreach ($cards as $card) {
            $this->register($card);
        }
    }

    /**
     * Two cards sharing a handle would quietly overwrite each other in the
     * stats JSON, so that is an error rather than a surprise.
     */
    public function register(StatCard $card): static
    {
        $handle = $card->handle();

        if (isset($this->cards[$handle])) {
            throw new \InvalidArgumentException("A stat card is already registered as [{$handle}].");
        }

        $this->cards[$handle] = $card;

        return $this;
    }

    /**
     * @return Collection<string, StatCard>
     */
    public function all(): Collection
    {
        return new Collection($this->cards);
    }

    public function find(string $handle): ?StatCard
    {
        return $this->cards[$handle] ?? null;
    }
}
