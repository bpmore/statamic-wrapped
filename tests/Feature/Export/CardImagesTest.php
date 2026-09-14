<?php

use Bpmore\Wrapped\Export\CardImages;
use Bpmore\Wrapped\Export\ChromeRenderer;
use Bpmore\Wrapped\Export\ImageRenderer;
use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\Snapshots\Period;
use Bpmore\Wrapped\Snapshots\Snapshot;
use Bpmore\Wrapped\Tests\Fixtures\FakeImageRenderer;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $stats
 */
function exportable(?array $stats = null): Snapshot
{
    return new Snapshot([
        'site' => 'default',
        'period' => Period::Year,
        'period_key' => '2026',
        'history_source' => 'logbook',
        'confidence' => Confidence::High,
        'stats' => $stats ?? [
            'entries_published' => ['count' => 47, 'previous' => 21],
            'busiest_time' => ['day' => 2, 'day_count' => 20, 'hour' => 14, 'hour_count' => 18, 'from' => 40],
        ],
        'generated_at' => CarbonImmutable::parse('2026-12-01 09:00:00'),
    ]);
}

function imagesWith(FakeImageRenderer $renderer): CardImages
{
    app()->instance(ImageRenderer::class, $renderer);

    return app(CardImages::class);
}

it('renders one card at social size', function () {
    $renderer = new FakeImageRenderer;

    $png = imagesWith($renderer)->card(exportable(), 'busiest_time');

    expect($png)->toBe('PNG:1200x630')
        ->and($renderer->width)->toBe(1200)
        ->and($renderer->height)->toBe(630)
        ->and($renderer->html)->toContain('You publish most on Tuesday, and most often around 2 pm.')
        ->and($renderer->html)->toContain('When you publish')
        ->and($renderer->html)->toContain('2026');
});

it('renders a combined summary', function () {
    $renderer = new FakeImageRenderer;

    $png = imagesWith($renderer)->summary(exportable());

    expect($png)->toBe('PNG:1200x1500')
        ->and($renderer->html)->toContain('2026 Wrapped')
        ->and($renderer->html)->toContain('Entries published')
        ->and($renderer->html)->toContain('When you publish');
});

it('needs no network, so nothing is fetched into the picture', function () {
    $renderer = new FakeImageRenderer;

    imagesWith($renderer)->card(exportable(), 'entries_published');

    expect($renderer->html)->not->toContain('http://')
        ->and($renderer->html)->not->toContain('https://')
        ->and($renderer->html)->not->toContain('<script');
});

it('escapes card text rather than letting it become markup', function () {
    $renderer = new FakeImageRenderer;

    imagesWith($renderer)->card(exportable([
        'longest_entry' => ['id' => 'x', 'title' => '<script>alert(1)</script>', 'collection' => 'blog', 'words' => 900],
    ]), 'longest_entry');

    expect($renderer->html)->not->toContain('<script>alert')
        ->and($renderer->html)->toContain('&lt;script&gt;');
});

it('refuses a card this wrapped does not have', function () {
    expect(fn () => imagesWith(new FakeImageRenderer)->card(exportable(), 'nonsense'))
        ->toThrow(RuntimeException::class, 'no [nonsense] card');
});

it('refuses to summarise a wrapped with nothing on it', function () {
    expect(fn () => imagesWith(new FakeImageRenderer)->summary(exportable([])))
        ->toThrow(RuntimeException::class, 'nothing on it');
});

it('names the file so it can be found again', function () {
    $images = imagesWith(new FakeImageRenderer);

    expect($images->filename(exportable(), 'busiest_time'))->toBe('wrapped-2026-default-busiest_time.png')
        ->and($images->filename(exportable()))->toBe('wrapped-2026-default.png');
});

it('lists what can be exported, in screen order', function () {
    expect(imagesWith(new FakeImageRenderer)->exportable(exportable()))
        ->toBe(['entries_published', 'busiest_time']);
});

it('reports itself unavailable without a browser', function () {
    expect(imagesWith(new FakeImageRenderer(available: false))->isAvailable())->toBeFalse()
        ->and(imagesWith(new FakeImageRenderer)->isAvailable())->toBeTrue();
});

describe('the chrome renderer', function () {
    it('is unavailable when the configured binary is not there', function () {
        config(['wrapped.images.chrome' => '/definitely/not/chrome']);

        expect((new ChromeRenderer)->isAvailable())->toBeFalse();
    });

    it('says what to do when it cannot find a browser', function () {
        config(['wrapped.images.chrome' => '/definitely/not/chrome']);

        expect(fn () => (new ChromeRenderer)->render('<p>hi</p>', 100, 100))
            ->toThrow(RuntimeException::class, 'wrapped.images.chrome');
    });

    it('really does produce a png', function () {
        $renderer = new ChromeRenderer;

        if (! $renderer->isAvailable()) {
            test()->markTestSkipped('No Chrome on this machine.');
        }

        $png = $renderer->render('<html><body style="background:#000">hi</body></html>', 400, 200);

        expect(substr($png, 1, 3))->toBe('PNG')
            ->and(strlen($png))->toBeGreaterThan(100);
    });
});

describe('the chrome renderer, as a sheet', function () {
    it('captures several pages in one launch and keeps the footer cell transparent', function () {
        $renderer = new ChromeRenderer;

        if (! $renderer->isAvailable()) {
            test()->markTestSkipped('No Chrome on this machine.');
        }

        $opaque = '<html><body style="margin:0;background:#123456;width:40px;height:20px"></body></html>';
        $clear = '<html><body style="margin:0;background:transparent;width:40px;height:20px"></body></html>';

        $png = $renderer->renderSheet([$opaque, $opaque, $clear], 40, 20, 2, transparent: true);

        // 2 columns, 2 rows of 40x20 at 1x: 80x40, RGBA.
        expect(substr($png, 1, 3))->toBe('PNG')
            ->and(unpack('N', substr($png, 16, 4))[1])->toBe(80)
            ->and(unpack('N', substr($png, 20, 4))[1])->toBe(40)
            ->and(ord($png[25]))->toBe(6);
    });
});
