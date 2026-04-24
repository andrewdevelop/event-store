<?php

namespace Core\EventStore\Tests;

use Core\EventStore\Drivers\FilesystemAdapter;
use Core\EventStore\Drivers\InMemoryAdapter;
use Core\EventStore\Drivers\PgsqlAdapter;
use Core\EventStore\EventStoreManager;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Mockery;

class EventStoreManagerTest extends TestCase
{
    public function test_it_creates_in_memory_driver()
    {
        $manager = new EventStoreManager(app());
        $driver = $manager->driver('in_memory');
        $this->assertInstanceOf(InMemoryAdapter::class, $driver);
    }

    public function test_it_creates_filesystem_driver()
    {
        Config::set('eventstore.connections.filesystem.database', __DIR__ . '/test_events.json');
        
        $manager = new EventStoreManager(app());
        $driver = $manager->driver('filesystem');
        $this->assertInstanceOf(FilesystemAdapter::class, $driver);
    }

    public function test_it_creates_pgsql_driver()
    {
        $manager = new EventStoreManager(app());
        
        // Mock connection factory in container
        $factory = Mockery::mock(\Illuminate\Database\Connectors\ConnectionFactory::class);
        $factory->shouldReceive('make')->andReturn(Mockery::mock(\Illuminate\Database\Connection::class));
        app()->instance(\Illuminate\Database\Connectors\ConnectionFactory::class, $factory);
        
        $driver = $manager->driver('pgsql');
        $this->assertInstanceOf(PgsqlAdapter::class, $driver);
    }

    public function test_it_throws_exception_if_no_default_driver_specified()
    {
        $manager = new EventStoreManager(app());
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No Eventstore driver was specified.');
        
        $manager->getDefaultDriver();
    }
}
