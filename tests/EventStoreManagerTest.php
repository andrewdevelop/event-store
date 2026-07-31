<?php

namespace Core\EventStore\Tests;

use Core\EventStore\Drivers\FilesystemAdapter;
use Core\EventStore\Drivers\InMemoryAdapter;
use Core\EventStore\Drivers\PgsqlAdapter;
use Core\EventStore\EventStoreManager;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use InvalidArgumentException;
use Mockery;

class EventStoreManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_creates_in_memory_driver()
    {
        $manager = new EventStoreManager($this->container);
        $driver = $manager->driver('in_memory');
        $this->assertInstanceOf(InMemoryAdapter::class, $driver);
    }

    public function test_it_creates_filesystem_driver()
    {
        $this->config->set('eventstore.connections.filesystem.database', __DIR__ . '/test_events.json');

        $manager = new EventStoreManager($this->container);
        $driver = $manager->driver('filesystem');
        $this->assertInstanceOf(FilesystemAdapter::class, $driver);
    }

    public function test_it_creates_pgsql_driver()
    {
        // Mock connection factory in container
        $factory = Mockery::mock(ConnectionFactory::class);
        $factory->shouldReceive('make')->andReturn(Mockery::mock(Connection::class));
        $this->container->instance(ConnectionFactory::class, $factory);

        $manager = new EventStoreManager($this->container);
        $driver = $manager->driver('pgsql');
        $this->assertInstanceOf(PgsqlAdapter::class, $driver);
    }

    public function test_it_throws_exception_if_no_default_driver_specified()
    {
        $manager = new EventStoreManager($this->container);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No Eventstore driver was specified.');

        $manager->getDefaultDriver();
    }
}
