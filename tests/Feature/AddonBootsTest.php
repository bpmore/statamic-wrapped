<?php

use Bpmore\Wrapped\ServiceProvider;
use Statamic\Statamic;

it('boots inside a statamic application', function () {
    expect(app()->getProvider(ServiceProvider::class))->toBeInstanceOf(ServiceProvider::class);
});

it('has statamic available', function () {
    expect(class_exists(Statamic::class))->toBeTrue();
});
