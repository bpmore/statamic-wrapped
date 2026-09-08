<?php

namespace Bpmore\Wrapped\Stats\Cards;

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistoryEvent;
use Bpmore\Wrapped\History\HistoryEventType;
use Bpmore\Wrapped\Stats\StatCard;
use Bpmore\Wrapped\Stats\StatContext;
use Statamic\Assets\Asset as AssetModel;
use Statamic\Facades\Asset as Assets;
use Throwable;

/**
 * How many files were uploaded, and how much they weigh.
 *
 * **Needs High, so this card only appears on a site running Logbook.** Nothing
 * else records an upload: revisions and entry data cover entries only, and an
 * asset's file mtime is the date of the last deploy on most sites. Counting
 * uploads from a directory listing would produce a confident number that is
 * simply wrong, so the card is left off instead.
 */
class AssetsUploadedCard implements StatCard
{
    public function handle(): string
    {
        return 'assets_uploaded';
    }

    public function requires(): Confidence
    {
        return Confidence::High;
    }

    public function compute(StatContext $context): ?array
    {
        $ids = $context->events()
            ->filter(fn (HistoryEvent $event) => $event->type === HistoryEventType::Created)
            ->filter(fn (HistoryEvent $event) => $event->itemType === 'asset')
            ->map(fn (HistoryEvent $event) => $event->itemId)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $bytes = 0;
        $weighed = 0;

        foreach ($ids as $id) {
            $size = $this->size($id);

            if ($size !== null) {
                $bytes += $size;
                $weighed++;
            }
        }

        return [
            'count' => $ids->count(),
            'bytes' => $bytes,
            // The total covers the uploads still on disk. One deleted since is
            // still an upload that happened, but it no longer has a size.
            'weighed' => $weighed,
        ];
    }

    protected function size(string $id): ?int
    {
        try {
            $asset = Assets::find($id);
        } catch (Throwable) {
            // A container that no longer exists must not fail the Wrapped.
            return null;
        }

        if (! $asset instanceof AssetModel) {
            return null;
        }

        return $asset->size();
    }
}
