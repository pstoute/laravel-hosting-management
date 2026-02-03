<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Pstoute\LaravelHosting\HostingServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            HostingServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Hosting' => \Pstoute\LaravelHosting\Facades\Hosting::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('hosting.default', 'forge');
        $app['config']->set('hosting.providers.forge.api_token', 'test-token');
    }
}
