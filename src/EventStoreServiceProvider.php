<?php

namespace Core\EventStore;

use Illuminate\Support\ServiceProvider;

use Core\EventSourcing\Contracts\EventStore;

class EventStoreServiceProvider extends ServiceProvider
{
    
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eventstore.php', 'eventstore');
        $this->app->configure('eventstore');
		
        $driver = $this->app->config->get('eventstore.connection');
        $config = $this->app->config->get("eventstore.connections.$driver");

        /** @var \Illuminate\Database\Connection */
        $factory = new \Illuminate\Database\Connectors\ConnectionFactory($this->app);
        $connection = $factory->make($config, $driver);

        $manager = new EventStoreManager($this->app);
        $eventstore = $manager->driver($driver);
        $eventstore->connection($connection);

    	$this->app->singleton(EventStore::class, function($app) use ($eventstore) {
	        return $eventstore;
    	});
        
    }


    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return [EventStore::class];
    }
}