<?php

use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\Export\VideoFrames;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Carbon\CarbonImmutable;

function framesWith(FakeImageRenderer $renderer): VideoFrames
{
    app()->instance(ImageRenderer::class, $renderer);

    return app(VideoFrames::class);
}

function aSnapshot(?string $startedAt = null): Snapshot
{
    return new Snapshot([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => [],
        'started_at' => $startedAt ? CarbonImmutable::parse($startedAt) : null,
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
    ]);
}

it('renders every frame portrait, at phone size', function () {
    $renderer = new FakeImageRenderer;
    $frames = framesWith($renderer);

    expect($frames->intro(aSnapshot())->png)->toBe('PNG:1080x1920');
    expect($frames->card(aSnapshot(), ['handle' => 'x', 'heading' => 'H', 'body' => 'B'])->png)->toBe('PNG:1080x1920');
    expect($frames->outro(aSnapshot())->png)->toBe('PNG:1080x1920');

    expect($renderer->width)->toBe(1080)->and($renderer->height)->toBe(1920);
});

it('gives each frame time in proportion to what is on it', function () {
    $frames = framesWith(new FakeImageRenderer);

    $short = $frames->card(aSnapshot(), ['handle' => 'x', 'heading' => 'Files', 'body' => '12 files.']);
    $long = $frames->card(aSnapshot(), ['handle' => 'y', 'heading' => 'When you publish', 'body' => 'You publish most on Tuesday, and most often around 2 pm.']);

    expect($short->seconds)->toBe(4.0)
        ->and($long->seconds)->toBeGreaterThan($short->seconds)
        ->and($long->seconds)->toBe(7.1);
});

it('renders the footer once, on a transparent ground, to be pinned over everything', function () {
    $renderer = new FakeImageRenderer;

    $png = framesWith($renderer)->footer(aSnapshot());

    expect($png)->toBe('PNG:1080x1920:transparent')
        ->and($renderer->transparent)->toBeTrue()
        ->and($renderer->html)->toContain('2026')
        ->and($renderer->html)->toContain('default')
        ->and($renderer->html)->toContain('background: transparent');
});

it('leaves the footer off the card frames, since it is pinned over them instead', function () {
    $renderer = new FakeImageRenderer;

    framesWith($renderer)->card(aSnapshot(), ['handle' => 'x', 'heading' => 'H', 'body' => 'B']);

    expect($renderer->html)->not->toContain('class="footer"');
});

it('opens with whose year it is', function () {
    $renderer = new FakeImageRenderer;

    framesWith($renderer)->intro(aSnapshot());

    expect($renderer->html)->toContain('2026')
        ->and($renderer->html)->toContain('Wrapped')
        ->and($renderer->html)->toContain('default');
});

it('puts one fact on a card frame', function () {
    $renderer = new FakeImageRenderer;

    framesWith($renderer)->card(aSnapshot(), [
        'handle' => 'busiest_time',
        'heading' => 'When you publish',
        'body' => 'You publish most on Tuesday, and most often around 2 pm.',
    ]);

    expect($renderer->html)->toContain('When you publish')
        ->and($renderer->html)->toContain('You publish most on Tuesday, and most often around 2 pm.');
});

it('closes quietly, without a call to action', function () {
    $renderer = new FakeImageRenderer;

    framesWith($renderer)->outro(aSnapshot());

    expect($renderer->html)->toContain('That was your year.')
        ->and(strtolower($renderer->html))->not->toContain('install')
        ->and(strtolower($renderer->html))->not->toContain('download');
});

it('frames a young site as "since June" on the intro and the pinned footer', function () {
    $renderer = new FakeImageRenderer;
    $frames = framesWith($renderer);

    $frames->intro(aSnapshot('2026-06-10'));
    expect($renderer->html)->toContain('Since June 2026');

    // The footer is over every frame, so "since June" is on every frame
    // without being drawn on each card.
    $frames->footer(aSnapshot('2026-06-10'));
    expect($renderer->html)->toContain('Since June 2026');
});

it('fetches nothing and escapes everything, like the card image', function () {
    $renderer = new FakeImageRenderer;

    framesWith($renderer)->card(aSnapshot(), [
        'handle' => 'x', 'heading' => '<b>H</b>', 'body' => '<script>alert(1)</script>',
    ]);

    expect($renderer->html)->not->toContain('http://')
        ->and($renderer->html)->not->toContain('https://')
        ->and($renderer->html)->not->toContain('<script>alert')
        ->and($renderer->html)->toContain('&lt;script&gt;');
});
