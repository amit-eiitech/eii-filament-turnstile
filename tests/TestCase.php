<?php

namespace Eii\FilamentTurnstile\Tests;

use Eii\FilamentTurnstile\TurnstileServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TurnstileServiceProvider::class,
        ];
    }
}
