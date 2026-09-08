<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\Stats\Concerns\DescribesEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Illuminate\Support\Collection;

/**
 * From first keystroke to live, at its quickest.
 *
 * **Needs High, so this card only appears on a site running Logbook.** It is
 * the only source that records a creation: revisions have no creation action,
 * and entry data has no creation timestamp at all. See the phase 1 summary.
 *
 * Only entries both created *and* published inside the period are considered.
 * Something started last December and published in January had a turnaround,
 * but not one this year's Wrapped can claim.
 *
 * Stored in seconds. Turning that into "42 minutes" is the CP screen's job, and
 * a stored "42 minutes" would not translate.
 */
class FastestTurnaroundCard implements StatCard
{
    use DescribesEntries;

    public function handle(): string
    {
        return 'fastest_turnaround';
    }

    public function requires(): Confidence
    {
        return Confidence::High;
    }

    public function compute(StatContext $context): ?array
    {
        $entries = $context->events()
            ->filter(fn (HistoryEvent $event) => $event->itemType === 'entry')
            ->groupBy(fn (HistoryEvent $event) => $event->itemId);

        $fastest = null;

        foreach ($entries as $id => $events) {
            $seconds = $this->turnaround($events);

            if ($seconds === null || ($fastest !== null && $seconds >= $fastest['seconds'])) {
                continue;
            }

            $entry = $this->findEntry((string) $id);

            if ($entry !== null) {
                $fastest = $this->describe($entry) + ['seconds' => $seconds];
            }
        }

        return $fastest;
    }

    /**
     * @param  Collection<int, HistoryEvent>  $events
     */
    protected function turnaround(Collection $events): ?int
    {
        $created = $events->firstWhere(fn (HistoryEvent $event) => $event->type === HistoryEventType::Created);
        $published = $events->firstWhere(fn (HistoryEvent $event) => $event->type === HistoryEventType::Published);

        if ($created === null || $published === null) {
            return null;
        }

        $seconds = $published->occurredAt->getTimestamp() - $created->occurredAt->getTimestamp();

        // A publication recorded before its own creation is a clock problem,
        // not a fast writer.
        return $seconds >= 0 ? $seconds : null;
    }
}
