<?php

namespace Bpmore\Wrapped\Export;

use Bpmore\Wrapped\Snapshots\Snapshot;

/**
 * The still frames a video is stitched from.
 *
 * Portrait, 1080x1920, because that is what Reels, Stories and TikTok expect
 * and a landscape card letterboxed into a phone screen is a waste of most of
 * it. Rendered by the same renderer as the card images, one PNG per frame, so
 * the video pipeline is "pictures, then FFmpeg" rather than a second way of
 * drawing a card.
 *
 * Every frame is text on a plain ground. That is the point: the video is the
 * facts, read at a phone's pace, with music under them.
 */
class VideoFrames
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    public function __construct(protected ImageRenderer $renderer) {}

    /**
     * @return string Raw PNG bytes.
     */
    public function intro(Snapshot $snapshot): string
    {
        return $this->render(['kind' => 'intro'], $snapshot);
    }

    /**
     * @param  array{handle: string, heading: string, body: string}  $card
     * @return string Raw PNG bytes.
     */
    public function card(Snapshot $snapshot, array $card): string
    {
        return $this->render([
            'kind' => 'card',
            'heading' => $card['heading'],
            'body' => $card['body'],
        ], $snapshot);
    }

    /**
     * @return string Raw PNG bytes.
     */
    public function outro(Snapshot $snapshot): string
    {
        return $this->render([
            'kind' => 'outro',
            'outro' => __('wrapped::messages.video.outro'),
        ], $snapshot);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function render(array $data, Snapshot $snapshot): string
    {
        $label = $snapshot->isFirstPeriod()
            ? __('wrapped::messages.since', ['month' => $snapshot->started_at->translatedFormat('F Y')])
            : $snapshot->period_key;

        $html = view('wrapped::export.frame', $data + [
            'period' => $label,
            'site' => $snapshot->site,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
        ])->render();

        return $this->renderer->render($html, self::WIDTH, self::HEIGHT);
    }
}
