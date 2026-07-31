<?php

namespace Core\EventStore\Tests;

use Closure;
use Core\EventSourcing\Contracts\EventStore;
use Core\EventStore\Drivers\InMemoryAdapter;
use Core\EventStore\EventStoreServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Mockery;

class EventStoreServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_registers_event_store_binding()
    {
        $config = new Repository([]);
        $config->set('eventstore.connection', 'in_memory');

        $app = Mockery::mock(Application::class);
        $app->shouldReceive('make')->with('config')->andReturn($config);
        $app->config = $config;
        $app->shouldReceive('singleton')
            ->with(EventStore::class, Mockery::type(Closure::class))
            ->andReturnUsing(function ($abstract, $closure) use (&$resolver) {
                $resolver = $closure;
            });
        $app->shouldReceive('make')
            ->with(InMemoryAdapter::class, Mockery::type('array'))
            ->andReturn(new InMemoryAdapter());

        $provider = new EventStoreServiceProvider($app);
        $provider->register();

        $this->assertNotNull($resolver);
        $this->assertInstanceOf(InMemoryAdapter::class, $resolver());
    }

    public function test_it_provides_event_store_class()
    {
        $app = Mockery::mock(Application::class);
        $provider = new EventStoreServiceProvider($app);

        $this->assertContains(EventStore::class, $provider->provides());
    }
}
