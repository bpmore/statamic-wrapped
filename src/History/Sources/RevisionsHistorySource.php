<?php

namespace Bpmore\Wrapped\History\Sources;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\History\HistorySource;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Collection;
use Statamic\Facades\Revision as Revisions;
use Statamic\Facades\YAML;
use Throwable;

/**
 * History from Statamic's own revisions.
 *
 * Revisions are Pro-only, opt-in per collection, and only entries are revisable
 * (`Statamic\Revisions\Revisable` is used by `Entry` and nothing else). Files
 * live at:
 *
 *     <revisions dir>/collections/<collection>/<site>/<entry id>/<timestamp>.yaml
 *
 * The filename is the unix timestamp, which is why this source can decide what
 * is in range before it opens a single file.
 *
 * Rated Partial rather than High even though the trail is real. Revisions are
 * switched on per collection, so a site can have a perfect record of its blog
 * and nothing at all for the other forty collections — and nothing on disk says
 * which case you are in. A confidently wrong "entries published this year" is
 * exactly what SPEC.md §1 says to avoid.
 *
 * Also worth knowing: `storage/statamic/revisions` is git-ignored by default, so
 * a deploy that rebuilds storage silently truncates this history.
 *
 * @see https://statamic.dev/revisions
 */
class RevisionsHistorySource implements HistorySource
{
    /**
     * Statamic's revision actions. 'working' is the unsaved working copy, which
     * is a draft rather than something that happened, and is skipped by name.
     */
    protected const ACTIONS = [
        'publish' => HistoryEventType::Published,
        'unpublish' => HistoryEventType::Updated,
        'revision' => HistoryEventType::Updated,
    ];

    public function handle(): string
    {
        return 'revisions';
    }

    public function name(): string
    {
        return 'Statamic Revisions';
    }

    public function confidence(): Confidence
    {
        return Confidence::Partial;
    }

    /**
     * Availability is decided by the files, not by whether the site is on Pro
     * today. A site that dropped to Solo still has the revisions it wrote, and
     * they are no less true.
     */
    public function isAvailable(): bool
    {
        foreach ($this->files() as $ignored) {
            return true;
        }

        return false;
    }

    public function events(CarbonImmutable $from, CarbonImmutable $to, ?string $site = null): Collection
    {
        $events = new Collection;

        foreach ($this->files($site) as $file) {
            // The filename is the timestamp, so most files can be ruled out
            // without being read.
            if ($file['timestamp'] < $from->getTimestamp() || $file['timestamp'] > $to->getTimestamp()) {
                continue;
            }

            $event = $this->toEvent($file);

            // Re-checked against the window because the recorded date wins over
            // the filename, and the two could disagree.
            if ($event && $event->occurredAt->betweenIncluded($from, $to)) {
                $events->push($event);
            }
        }

        return $events
            ->sortBy(fn (HistoryEvent $event) => $event->occurredAt->getTimestamp())
            ->values();
    }

    public function earliestEvent(?string $site = null): ?CarbonImmutable
    {
        $earliest = null;

        // Every file is read, rather than just taking the lowest filename. A
        // file we cannot turn into an event must not set the date the whole
        // Wrapped is framed around.
        foreach ($this->files($site) as $file) {
            $event = $this->toEvent($file);

            if ($event === null) {
                continue;
            }

            if ($earliest === null || $event->occurredAt->lessThan($earliest)) {
                $earliest = $event->occurredAt;
            }
        }

        return $earliest;
    }

    /**
     * Every saved revision file, newest and oldest alike, with what the path
     * already tells us. Working copies are excluded by the glob.
     *
     * @return Generator<int, array{path: string, timestamp: int, collection: string, site: string, id: string}>
     */
    protected function files(?string $site = null): Generator
    {
        if (! $directory = $this->directory()) {
            return;
        }

        $pattern = sprintf('%s/collections/*/%s/*/*.yaml', $directory, $site ?? '*');

        foreach (glob($pattern) ?: [] as $path) {
            $segments = explode('/', $path);
            $timestamp = basename($path, '.yaml');

            // working.yaml, and anything else not named for a timestamp.
            if (! ctype_digit($timestamp)) {
                continue;
            }

            yield [
                'path' => $path,
                'timestamp' => (int) $timestamp,
                'collection' => $segments[count($segments) - 4],
                'site' => $segments[count($segments) - 3],
                'id' => $segments[count($segments) - 2],
            ];
        }
    }

    /**
     * @param  array{path: string, timestamp: int, collection: string, site: string, id: string}  $file
     */
    protected function toEvent(array $file): ?HistoryEvent
    {
        $revision = $this->parse($file['path']);

        if ($revision === null) {
            return null;
        }

        $action = $this->string($revision['action'] ?? null) ?? 'revision';
        $type = self::ACTIONS[$action] ?? null;

        if ($type === null) {
            return null;
        }

        $date = $revision['date'] ?? null;

        return new HistoryEvent(
            type: $type,
            itemType: 'entry',
            itemId: $file['id'],
            occurredAt: $this->timestamp(is_numeric($date) ? (int) $date : $file['timestamp']),
            author: $this->string($revision['user'] ?? null),
            site: $file['site'],
            meta: array_filter([
                'action' => $action,
                'collection' => $file['collection'],
                'message' => $this->string($revision['message'] ?? null),
                'title' => $this->string($revision['attributes']['data']['title'] ?? null),
            ], fn ($value) => $value !== null),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parse(string $path): ?array
    {
        try {
            $parsed = YAML::file($path)->parse();
        } catch (Throwable) {
            // A half-written or hand-edited revision file is not worth failing
            // a whole Wrapped over.
            return null;
        }

        return $parsed;
    }

    /**
     * Where Statamic keeps revisions, asked of Statamic rather than rebuilt from
     * the several config keys that feed it.
     */
    protected function directory(): ?string
    {
        try {
            $directory = Revisions::directory();
        } catch (Throwable) {
            return null;
        }

        return $directory === '' ? null : rtrim($directory, '/');
    }

    /**
     * Statamic reads revision dates in the app timezone; so do we, or every
     * "busiest hour" would be an hour out.
     */
    protected function timestamp(int $timestamp): CarbonImmutable
    {
        $timezone = config('app.timezone');

        return CarbonImmutable::createFromTimestamp($timestamp, is_string($timezone) ? $timezone : null);
    }

    protected function string(mixed $value): ?string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
