<?php

namespace Bpmore\Wrapped\Tests;

use Bpmore\Wrapped\ServiceProvider;
use Illuminate\Support\Facades\File;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        // The addon's settings are a real file in the app's resource path,
        // and shared state: the first test to save one would decide the look
        // for every test after it. Every test starts from "nobody has opened
        // the settings screen".
        File::delete(resource_path('addons/wrapped.yaml'));
    }
}
