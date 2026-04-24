<?php

namespace Core\EventStore\Tests;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventStore\Drivers\InMemoryAdapter;
use Illuminate\Support\Facades\Config;

class EventStoreServiceProviderTest extends TestCase
{
    public function test_it_registers_event_store_binding()
    {
        Config::set('eventstore.connection', 'in_memory');
        
        $this->assertTrue(app()->bound(EventStore::class));
        $this->assertInstanceOf(InMemoryAdapter::class, app(EventStore::class));
    }
    
    public function test_it_provides_event_store_class()
    {
        $provider = app()->getProvider(\Core\EventStore\EventStoreServiceProvider::class);
        $this->assertContains(EventStore::class, $provider->provides());
    }
}
