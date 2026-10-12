<?php

namespace Tests;

use App\Infrastructure\Secrets\KeyCanary;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $canary = (string) config('rahandaz.canary_file');

        if (! is_file($canary)) {
            app(KeyCanary::class)->write($canary);
        }
    }
}
