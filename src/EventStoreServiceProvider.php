<?php

namespace Core\EventStore;

use Core\EventSourcing\Contracts\EventStore;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class EventStoreServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->configure();

        $manager = new EventStoreManager($this->app);

        $this->app->singleton(EventStore::class, fn() => $manager->driver($this->app->config->get('eventstore.connection')));
    }

    protected function configure(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/eventstore.php', 'eventstore');
        
        if (method_exists($this->app, 'configure')) {
            $this->app->configure('eventstore');
        }
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