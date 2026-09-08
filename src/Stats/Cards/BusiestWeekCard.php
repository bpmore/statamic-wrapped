<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\Stats\Concerns\ReadsPublishedEntries;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The week the most went out.
 *
 * Keyed on the Monday the week starts, not on its ISO number alone: ISO week 1
 * can fall in either of two calendar years, so "week 1" on its own is ambiguous
 * in stored data. The number is reported too, because that is what people say.
 */
class BusiestWeekCard implements StatCard
{
    use ReadsPublishedEntries;

    public function handle(): string
    {
        return 'busiest_week';
    }

    public function requires(): Confidence
    {
        return Confidence::Partial;
    }

    public function compute(StatContext $context): ?array
    {
        $counts = $this->firstPublications($context->events())
            ->countBy(fn (HistoryEvent $event) => $event->occurredAt
                ->startOfWeek(CarbonInterface::MONDAY)
                ->toDateString());

        if ($counts->isEmpty()) {
            return null;
        }

        $starts = (string) $counts->sortDesc()->keys()->first();

        return [
            'starts_on' => $starts,
            'week' => CarbonImmutable::parse($starts)->isoWeek(),
            'count' => $counts->get($starts),
        ];
    }
}
