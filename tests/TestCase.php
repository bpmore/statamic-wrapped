<?php

namespace Bpmore\Wrapped\Tests;

use Bpmore\Wrapped\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
