<?php

use Bpmore\Wrapped\History\Confidence;

it('ranks high above partial above low', function () {
    expect(Confidence::High->rank())->toBeGreaterThan(Confidence::Partial->rank())
        ->and(Confidence::Partial->rank())->toBeGreaterThan(Confidence::Low->rank());
});

it('meets a requirement it equals or beats', function () {
    expect(Confidence::High->meets(Confidence::Partial))->toBeTrue()
        ->and(Confidence::Partial->meets(Confidence::Partial))->toBeTrue()
        ->and(Confidence::Low->meets(Confidence::Partial))->toBeFalse();
});
