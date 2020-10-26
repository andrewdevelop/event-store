<?php

namespace Core\EventStore;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

use Core\EventSourcing\Contracts\EventStore;

class EventStoreServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eventstore.php', 'eventstore');
        $this->app->configure('eventstore');
		
        $driver = $this->app->config->get('eventstore.connection');


        $manager = new EventStoreManager($this->app);
        $this->app->singleton(EventStore::class, function($app) use ($manager, $driver) {
            return $manager->driver($driver);
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