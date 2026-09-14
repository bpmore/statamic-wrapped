<?php

namespace Bpmore\Wrapped\Export;

use Bpmore\Wrapped\Snapshots\Period;
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

    /** Cells across the contact sheet. Three keeps ten frames at 3240x7680, comfortably inside what Chrome will capture. */
    public const SHEET_COLUMNS = 3;

    public function __construct(
        protected ImageRenderer $renderer,
        protected VideoRenderer $video,
        protected Theme $theme,
        protected VideoSpec $spec = new VideoSpec,
    ) {}

    /**
     * Every frame for a video, plus the footer, from one browser launch.
     *
     * The intro, each card, the outro and the footer are laid out on one
     * contact sheet, captured once, and sliced apart. Rendering them one at a
     * time cost a browser start per frame — nearly all of a thirty-second
     * render — and produced exactly the same pixels.
     *
     * @param  list<array{handle: string, heading: string, body: string}>  $cards
     * @return array{frames: list<Frame>, footer: string}
     */
    public function sheet(Snapshot $snapshot, array $cards): array
    {
        $pages = [['kind' => 'intro'], ...array_map(
            fn (array $card) => ['kind' => 'card', 'heading' => $card['heading'], 'body' => $card['body']],
            $cards,
        ), ['kind' => 'outro', 'outro' => __('wrapped::messages.video.outro')], ['kind' => 'footer']];

        $htmls = array_map(fn (array $page) => $this->html($page, $snapshot), $pages);

        // Transparent, so the footer cell keeps its alpha. Every other page
        // paints its own opaque background, so they are unaffected.
        $sheet = $this->renderer->renderSheet($htmls, self::WIDTH, self::HEIGHT, self::SHEET_COLUMNS, transparent: true);
        $cells = $this->video->split($sheet, self::WIDTH, self::HEIGHT, count($pages), self::SHEET_COLUMNS);

        $footer = array_pop($cells);
        $frames = [];

        foreach ($cells as $i => $png) {
            $page = $pages[$i];

            $frames[] = new Frame($png, match ($page['kind']) {
                'card' => $this->spec->secondsFor($page['body']),
                default => $this->spec->titleSeconds,
            });
        }

        return ['frames' => $frames, 'footer' => $footer];
    }

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
            : Period::label($snapshot->period_key);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function render(array $data, Snapshot $snapshot, bool $transparent = false): string
    {
        return $this->renderer->render($this->html($data, $snapshot), self::WIDTH, self::HEIGHT, $transparent);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function html(array $data, Snapshot $snapshot): string
    {
        return view('wrapped::export.frame', $data + [
            'theme' => $this->theme,
            'period' => $this->label($snapshot),
            'site' => $snapshot->site,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
        ])->render();
    }
}
