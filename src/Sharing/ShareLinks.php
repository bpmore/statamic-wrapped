<?php

namespace Bpmore\Wrapped\Sharing;

use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Stats\CardGate;
use Bpmore\Wrapped\Stats\CardPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Making, listing and revoking public links.
 *
 * Off unless the site turns it on. SPEC.md §5 said there would never be a
 * public URL, because a leaked link to a team's publishing stats is a
 * support problem and a privacy problem. That is still true for most sites,
 * which is why the default is off. A newsroom that wants to post "we
 * published 4,000 stories this year" is a different site with a different
 * decision, and this is how it makes it: one setting, one permission, and a
 * link that can be taken back.
 */
class ShareLinks
{
    /** Longest a link may be set to live, in days. Long enough for "the year"; not forever by accident. */
    public const MAX_DAYS = 400;

    public function __construct(
        protected CardPresenter $presenter,
        protected CardGate $gate,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('wrapped.share.enabled', false);
    }

    /**
     * Whether this user may make and revoke links. Needs the setting on and
     * the permission both; either alone is not a decision to publish.
     */
    public function canShare(): bool
    {
        return $this->enabled() && Gate::allows(CardGate::SHARE);
    }

    /**
     * Cut a public copy of a snapshot.
     *
     * The people cards go in only when asked for *and* this user may see
     * them: a link is a way of showing something to everyone, so it can
     * never show more than its maker could.
     */
    public function publish(Snapshot $snapshot, string $label, bool $people = false, ?int $days = null, ?string $by = null): Share
    {
        if (! $this->canShare()) {
            throw new RuntimeException('Public sharing is off, or this user may not share.');
        }

        if ($days !== null && ($days < 1 || $days > self::MAX_DAYS)) {
            throw new RuntimeException(sprintf('A link can last between 1 and %d days.', self::MAX_DAYS));
        }

        $people = $people && $this->gate->canViewPeople();

        $cards = array_values(array_filter(
            $this->presenter->present($snapshot->stats),
            fn (array $card) => $people || ! $this->gate->isAboutPeople($card['handle']),
        ));

        $now = CarbonImmutable::now();

        return Share::create([
            'token' => bin2hex(random_bytes(20)),
            'site' => $snapshot->site,
            'period_key' => $snapshot->period_key,
            'label' => $label,
            'cards' => array_map(fn (array $card) => [
                'handle' => $card['handle'],
                'heading' => $card['heading'],
                'body' => $card['body'],
            ], $cards),
            'people' => $people,
            'created_by' => $by,
            'created_at' => $now,
            'expires_at' => $days === null ? null : $now->addDays($days),
        ]);
    }

    /**
     * The links still serving for a snapshot, newest first.
     *
     * @return Collection<int, Share>
     */
    public function liveFor(Snapshot $snapshot): Collection
    {
        return Share::query()
            ->live()
            ->where('site', $snapshot->site)
            ->where('period_key', $snapshot->period_key)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * The share a public request is for, or null when there is nothing to
     * show: unknown token, revoked, expired, or sharing switched off since.
     * All four are the same 404 to a visitor; a link that stopped working
     * should not explain why to whoever holds it.
     */
    public function resolve(string $token): ?Share
    {
        if (! $this->enabled()) {
            return null;
        }

        $share = Share::query()->where('token', $token)->first();

        return $share?->isLive() ? $share : null;
    }
}
