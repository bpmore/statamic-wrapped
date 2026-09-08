<?php

namespace Bpmore\Wrapped\History\Sources;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\HistorySource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * History from Statamic Logbook's audit trail.
 *
 * The best source we have: a real row per act, with the user who did it and a
 * before/after diff. Logbook is a free addon, so this is only available when a
 * site has chosen to install it.
 *
 * Availability is decided by the table, not by whether the addon is currently
 * installed. A site that removed Logbook still has the history it recorded, and
 * that history is no less true.
 *
 * @see https://statamic.com/addons/emran-alhaddad/statamic-logbook
 */
class LogbookHistorySource implements HistorySource
{
    public const TABLE = 'logbook_audit_logs';

    /**
     * Logbook keeps its logs on their own connection so the addon can be
     * removed by dropping one database. Mirrors its DbConnectionResolver.
     */
    public const CONNECTION = 'logbook';

    /**
     * Logbook writes `statamic.<subject>.<operation>`. These are the operations
     * that are history; the rest (logins, two-factor, impersonation) are audit
     * signal but tell us nothing about content.
     */
    protected const OPERATIONS = [
        'created' => HistoryEventType::Created,
        'uploaded' => HistoryEventType::Created,
        'published' => HistoryEventType::Published,
        'updated' => HistoryEventType::Updated,
        'saved' => HistoryEventType::Updated,
        'replaced' => HistoryEventType::Updated,
        'unpublished' => HistoryEventType::Updated,
        'deleted' => HistoryEventType::Deleted,
    ];

    public function handle(): string
    {
        return 'logbook';
    }

    public function name(): string
    {
        return 'Statamic Logbook';
    }

    public function confidence(): Confidence
    {
        return Confidence::High;
    }

    public function isAvailable(): bool
    {
        try {
            return Schema::connection($this->connection())->hasTable(self::TABLE);
        } catch (Throwable) {
            // A misconfigured or missing Logbook connection is not an error
            // here; it just means this source cannot be read.
            return false;
        }
    }

    public function events(CarbonImmutable $from, CarbonImmutable $to, ?string $site = null): Collection
    {
        if (! $this->isAvailable()) {
            return new Collection;
        }

        $query = $this->scopedQuery($site)->whereBetween('created_at', [$from, $to]);

        $events = new Collection;

        foreach ($query->cursor() as $row) {
            if ($event = $this->toEvent($row)) {
                $events->push($event);
            }
        }

        return $events;
    }

    public function earliestEvent(?string $site = null): ?CarbonImmutable
    {
        if (! $this->isAvailable()) {
            return null;
        }

        // The oldest row we can turn into an event, rather than the oldest row.
        // A sign-in from 2019 is not evidence that anything was published.
        foreach ($this->scopedQuery($site)->cursor() as $row) {
            if ($event = $this->toEvent($row)) {
                return $event->occurredAt;
            }
        }

        return null;
    }

    /**
     * Oldest first, so earliestEvent() can stop at the first usable row.
     */
    protected function scopedQuery(?string $site): Builder
    {
        $query = DB::connection($this->connection())
            ->table(self::TABLE)
            ->select('action', 'user_id', 'subject_type', 'subject_id', 'subject_handle', 'subject_title', 'meta', 'created_at')
            ->orderBy('created_at');

        if ($site !== null) {
            $query->where('meta->site', $site);
        }

        return $query;
    }

    /**
     * Register and name Logbook's connection, or null to use the app default
     * when the site has no Logbook config at all.
     */
    protected function connection(): ?string
    {
        $config = config('logbook.db.connection');

        if (! is_array($config) || blank($config['driver'] ?? null)) {
            return null;
        }

        config(['database.connections.'.self::CONNECTION => $config]);

        return self::CONNECTION;
    }

    /**
     * Rows we cannot honestly place — an unmapped action, or no subject to
     * attach it to — are dropped rather than guessed at.
     */
    protected function toEvent(object $row): ?HistoryEvent
    {
        $type = $this->eventType($this->string($row->action ?? null) ?? '');

        if ($type === null) {
            return null;
        }

        $id = $this->string($row->subject_id ?? null) ?? $this->string($row->subject_handle ?? null);
        $itemType = $this->string($row->subject_type ?? null);

        if ($id === null || $itemType === null) {
            return null;
        }

        $meta = $this->decodeMeta($row->meta ?? null);

        return new HistoryEvent(
            type: $type,
            itemType: $itemType,
            itemId: $id,
            occurredAt: CarbonImmutable::parse($this->string($row->created_at ?? null) ?? 'now'),
            author: $this->string($row->user_id ?? null),
            site: $this->string($meta['site'] ?? null),
            meta: array_filter([
                'action' => $this->string($row->action ?? null),
                'collection' => $this->string($meta['collection'] ?? null),
                'uri' => $this->string($meta['uri'] ?? null),
                'title' => $this->string($row->subject_title ?? null),
            ], fn ($value) => $value !== null),
        );
    }

    protected function eventType(string $action): ?HistoryEventType
    {
        $position = strrpos($action, '.');
        $operation = $position === false ? $action : substr($action, $position + 1);

        return self::OPERATIONS[$operation] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeMeta(mixed $meta): array
    {
        if (! is_string($meta) || $meta === '') {
            return [];
        }

        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Blank and non-scalar values become null, so "we don't know" never
     * arrives at the stat layer disguised as an empty string.
     */
    protected function string(mixed $value): ?string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
