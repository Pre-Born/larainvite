<?php

namespace Junaidnasir\Larainvite\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Junaidnasir\Larainvite\Facades\Invite;
use Junaidnasir\Larainvite\LaraInviteServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [LaraInviteServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return ['Invite' => Invite::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../src/Migrations');
    }
}
