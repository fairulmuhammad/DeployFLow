<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests assert Inertia props, not compiled assets. The Vite manifest is
        // produced by the frontend job, so the PHP job must not depend on it.
        $this->withoutVite();
    }
}
