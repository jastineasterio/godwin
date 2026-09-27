<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests assert on Inertia payloads, not compiled assets, so the
     * Vite manifest is stubbed out (keeps tests fast and independent of
     * `npm run build`).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
