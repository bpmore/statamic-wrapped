<?php

namespace Bpmore\Wrapped\Snapshots;

use Bpmore\Wrapped\History\Confidence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * One generated Wrapped, cached.
 *
 * Aggregating across forty thousand entries on page load will not do, so the
 * numbers are computed in a job and the result kept here as JSON — see
 * SPEC.md §4.
 *
 * `history_source` and `confidence` are stored alongside the stats rather than
 * derived on read, because they describe *this* snapshot. A site that installs
 * Logbook in March does not retroactively improve the Wrapped it generated in
 * January, and the UI must not claim otherwise.
 *
 * @property string $site
 * @property Period $period
 * @property string $period_key
 * @property string $history_source
 * @property Confidence $confidence
 * @property array<string, mixed> $stats
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable $generated_at
 * @property string|null $generated_by
 */
class Snapshot extends Model
{
    protected $table = 'wrapped_snapshots';

    /**
     * `generated_at` is the only time this row has, and regenerating updates
     * it, so a created_at/updated_at pair would say nothing new.
     */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'site',
        'period',
        'period_key',
        'history_source',
        'confidence',
        'stats',
        'started_at',
        'generated_at',
        'generated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => Period::class,
            'confidence' => Confidence::class,
            'stats' => 'array',
            'started_at' => 'immutable_datetime',
            'generated_at' => 'immutable_datetime',
        ];
    }

    /**
     * A site younger than the period it was wrapped for.
     */
    public function isFirstPeriod(): bool
    {
        return $this->started_at !== null;
    }

    /**
     * The first day this snapshot covers, from its key. For ordering a mix of
     * years, quarters and months; the stats themselves already know.
     */
    public function startsOn(): CarbonImmutable
    {
        [$period, $year, $part] = Period::fromKey($this->period_key)
            ?? throw new RuntimeException("[{$this->period_key}] is not a period key this version understands.");

        return $period->window($year, $part)[0];
    }

    /**
     * Is this snapshot's history good enough for a card that needs $required?
     * Cards that answer false are omitted rather than guessed.
     */
    public function supports(Confidence $required): bool
    {
        return $this->confidence->meets($required);
    }
}
