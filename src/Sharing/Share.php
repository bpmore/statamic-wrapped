<?php

namespace Bpmore\Wrapped\Sharing;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One public link to one Wrapped, frozen at the moment it was made.
 *
 * Frozen on purpose. A regenerate must not silently change a page somebody
 * has already posted, and a card the editor left out must not appear later
 * because a permission changed. So the row keeps the cards as text, not a
 * pointer to the snapshot.
 *
 * @property string $token
 * @property string $site
 * @property string $period_key
 * @property string $label
 * @property list<array{handle: string, heading: string, body: string}> $cards
 * @property bool $people
 * @property string|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 */
class Share extends Model
{
    protected $table = 'wrapped_shares';

    /** `created_at` is set by hand; there is no updated_at because nothing here updates. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'token',
        'site',
        'period_key',
        'label',
        'cards',
        'people',
        'created_by',
        'created_at',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cards' => 'array',
            'people' => 'boolean',
            'created_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * Still serving: not revoked, and not past its expiry.
     */
    public function isLive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @param  Builder<Share>  $query
     * @return Builder<Share>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now()));
    }

    public function revoke(): void
    {
        if ($this->revoked_at === null) {
            $this->forceFill(['revoked_at' => CarbonImmutable::now()])->save();
        }
    }

    /**
     * The public URL. Absolute, because it is going to be pasted somewhere.
     */
    public function url(): string
    {
        return route('wrapped.share.show', ['token' => $this->token]);
    }
}
