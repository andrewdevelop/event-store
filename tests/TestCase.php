<?php

namespace Core\EventStore\Tests;

use Core\EventStore\EventStoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            EventStoreServiceProvider::class,
        ];
    }
}
