<?php

namespace Core\EventStore\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Container $container;

    protected Repository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new Repository([
            'eventstore' => require __DIR__ . '/../config/eventstore.php',
        ]);

        $this->container = new Container();
        $this->container->instance('config', $this->config);
        $this->container->instance(ConfigRepository::class, $this->config);
    }
}
