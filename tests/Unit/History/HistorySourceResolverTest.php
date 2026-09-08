<?php

use Bpmore\Wrapped\History\Confidence;
use Bpmore\Wrapped\History\HistorySourceResolver;
use Bpmore\Wrapped\Tests\Fixtures\FakeHistorySource;

it('picks the highest confidence source that is available', function () {
    $resolver = new HistorySourceResolver([
        new FakeHistorySource('mtime', Confidence::Low),
        new FakeHistorySource('logbook', Confidence::High),
        new FakeHistorySource('entry_data', Confidence::Partial),
    ]);

    expect($resolver->resolve()?->handle())->toBe('logbook')
        ->and($resolver->confidence())->toBe(Confidence::High);
});

it('skips sources that are not available', function () {
    $resolver = new HistorySourceResolver([
        new FakeHistorySource('logbook', Confidence::High, available: false),
        new FakeHistorySource('entry_data', Confidence::Partial),
        new FakeHistorySource('mtime', Confidence::Low),
    ]);

    expect($resolver->resolve()?->handle())->toBe('entry_data')
        ->and($resolver->confidence())->toBe(Confidence::Partial);
});

it('keeps the first registered source when confidence ties', function () {
    $resolver = new HistorySourceResolver([
        new FakeHistorySource('revisions', Confidence::High),
        new FakeHistorySource('logbook', Confidence::High),
    ]);

    expect($resolver->resolve()?->handle())->toBe('revisions');
});

it('resolves to nothing when no source is available', function () {
    $resolver = new HistorySourceResolver([
        new FakeHistorySource('logbook', Confidence::High, available: false),
    ]);

    expect($resolver->resolve())->toBeNull()
        ->and($resolver->confidence())->toBe(Confidence::Low);
});

it('resolves to nothing when nothing is registered', function () {
    expect((new HistorySourceResolver)->resolve())->toBeNull();
});

it('re-resolves after a source is registered', function () {
    $resolver = new HistorySourceResolver([
        new FakeHistorySource('mtime', Confidence::Low),
    ]);

    expect($resolver->resolve()?->handle())->toBe('mtime');

    $resolver->register(new FakeHistorySource('logbook', Confidence::High));

    expect($resolver->resolve()?->handle())->toBe('logbook');
});

it('reports which sources are available', function () {
    $resolver = new HistorySourceResolver([
        new FakeHistorySource('logbook', Confidence::High, available: false),
        new FakeHistorySource('mtime', Confidence::Low),
    ]);

    expect($resolver->sources())->toHaveCount(2)
        ->and($resolver->available()->map->handle()->all())->toBe(['mtime']);
});

it('reports whether the resolved source supports a card', function () {
    $resolver = new HistorySourceResolver([
        new FakeHistorySource('entry_data', Confidence::Partial),
    ]);

    $resolved = $resolver->resolve();

    expect($resolved?->supports(Confidence::Partial))->toBeTrue()
        ->and($resolved?->supports(Confidence::High))->toBeFalse()
        ->and($resolved?->name())->toBe('Entry_data');
});
