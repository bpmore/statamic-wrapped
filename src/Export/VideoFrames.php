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
 * facts, read at a phone's pace, with music under them. Each frame carries its
 * own time on screen, worked out from how much there is to read on it.
 */
class VideoFrames
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    public function __construct(
        protected ImageRenderer $renderer,
        protected VideoSpec $spec = new VideoSpec,
    ) {}

    public function intro(Snapshot $snapshot): Frame
    {
        return new Frame(
            $this->render(['kind' => 'intro'], $snapshot),
            $this->spec->titleSeconds,
        );
    }

    /**
     * @param  array{handle: string, heading: string, body: string}  $card
     */
    public function card(Snapshot $snapshot, array $card): Frame
    {
        return new Frame(
            $this->render(['kind' => 'card', 'heading' => $card['heading'], 'body' => $card['body']], $snapshot),
            // Timed on the sentence. The heading is a two-word label taken in
            // at a glance, and the floor already covers it.
            $this->spec->secondsFor($card['body']),
        );
    }

    public function outro(Snapshot $snapshot): Frame
    {
        $outro = __('wrapped::messages.video.outro');

        return new Frame(
            $this->render(['kind' => 'outro', 'outro' => $outro], $snapshot),
            $this->spec->titleSeconds,
        );
    }

    /**
     * The period and site, on a transparent ground, to be laid over every
     * frame. Rendered once; it never changes and it never moves.
     *
     * @return string Raw PNG bytes with alpha.
     */
    public function footer(Snapshot $snapshot): string
    {
        return $this->render(['kind' => 'footer'], $snapshot, transparent: true);
    }

    protected function label(Snapshot $snapshot): string
    {
        return $snapshot->isFirstPeriod()
            ? __('wrapped::messages.since', ['month' => $snapshot->started_at->translatedFormat('F Y')])
            : $snapshot->period_key;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function render(array $data, Snapshot $snapshot, bool $transparent = false): string
    {
        $html = view('wrapped::export.frame', $data + [
            'period' => $this->label($snapshot),
            'site' => $snapshot->site,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
        ])->render();

        return $this->renderer->render($html, self::WIDTH, self::HEIGHT, $transparent);
    }
}
