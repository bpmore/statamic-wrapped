<?php

namespace Bpmore\Wrapped\Stats;

use Bpmore\Wrapped\History\Confidence;

/**
 * One card on the Wrapped.
 *
 * Every card is independently testable and declares the quality of history it
 * needs. A card that needs more than the resolved source can offer is left off
 * the Wrapped entirely rather than computed from data that cannot support it —
 * confidently wrong stats are worse than fewer stats, and this is a product
 * people screenshot. See SPEC.md §1 and §7.
 */
interface StatCard
{
    /**
     * Stable key for this card in the snapshot's `stats` JSON. Stored data, so
     * it must not change once released.
     */
    public function handle(): string;

    /**
     * The least trustworthy history this card can honestly be built from.
     *
     * Be strict here rather than generous. A "busiest week" from file mtimes is
     * the date of the last deploy.
     */
    public function requires(): Confidence;

    /**
     * The card's data, or null when there is nothing worth showing.
     *
     * Null means "computed, and there is no story here" — a year with no
     * entries, a site with one collection. It does not mean "not allowed to
     * compute"; the registry decides that from {@see requires()} before this is
     * ever called.
     *
     * @return array<string, mixed>|null
     */
    public function compute(StatContext $context): ?array;
}
