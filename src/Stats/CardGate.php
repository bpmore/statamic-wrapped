<?php

namespace Bpmore\Wrapped\Stats;

use Illuminate\Support\Facades\Gate;

/**
 * Who may see which cards.
 *
 * Two permissions, from SPEC.md §8. `view wrapped` opens the screen and is
 * meant to be granted broadly. `view wrapped people` is narrower and covers
 * every card marked {@see AboutPeople}: those are the ones that can turn a
 * celebration into a performance review, so seeing them is a separate decision.
 *
 * Nothing here is about *computing* cards — the snapshot stores whatever the
 * generate command produced. This is purely about what a given viewer is shown.
 */
class CardGate
{
    public const VIEW = 'view wrapped';

    public const VIEW_PEOPLE = 'view wrapped people';

    /** Making public links. A separate decision from viewing: this one shows the numbers to everyone. */
    public const SHARE = 'share wrapped';

    public function __construct(protected StatCardRegistry $cards) {}

    /**
     * Through Laravel's Gate rather than the user directly: Statamic hooks its
     * permissions into the Gate (supers pass, roles are checked), and this is
     * the same path the `can:` route middleware takes, so the screen and the
     * cards on it can never disagree.
     */
    public function canView(): bool
    {
        return Gate::allows(self::VIEW);
    }

    /**
     * Nobody logged in means nobody may see who did what. Safe by default.
     */
    public function canViewPeople(): bool
    {
        return Gate::allows(self::VIEW_PEOPLE);
    }

    /**
     * The stats this viewer may be shown.
     *
     * @param  array<string, array<string, mixed>>  $stats
     * @return array<string, array<string, mixed>>
     */
    public function visible(array $stats): array
    {
        if ($this->canViewPeople()) {
            return $stats;
        }

        return array_filter(
            $stats,
            fn (string $handle) => ! $this->isAboutPeople($handle),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function isAboutPeople(string $handle): bool
    {
        return $this->cards->find($handle) instanceof AboutPeople;
    }
}
