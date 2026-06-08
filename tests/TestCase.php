<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests render Blade views that reference @vite(); without a built
        // manifest (no npm build in CI) this throws. Stub Vite in tests.
        $this->withoutVite();
    }
}
