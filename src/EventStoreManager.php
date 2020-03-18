<?php 

namespace Core\EventStore;

use Illuminate\Support\Manager;
use InvalidArgumentException;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventStore\Drivers\PostgresqlAdapter;
use Core\EventStore\Drivers\InMemoryAdapter;

class EventStoreManager extends Manager
{

    /**
     * Create an instance of the specified driver.
     * @return \Core\EventStore\Drivers\InMemoryAdapter
     */
    protected function createInMemoryDriver()
    {
        return $this->buildProvider(InMemoryAdapter::class);
    }    

    /**
     * Create an instance of the specified driver.
     * @return \Core\EventStore\Drivers\PostgresqlAdapter
     */
    protected function createPgsqlDriver()
    {
        return $this->buildProvider(PostgresqlAdapter::class);
    }

    /**
     * Build a provider instance.
     * @param  string  $provider
     * @param  array  $config
     * @return \Laravel\Socialite\Two\AbstractProvider
     */
    public function buildProvider($provider, $config = null)
    {
        return new $provider($this->app);
    }

    /**
     * Get the default driver name.
     * @throws \InvalidArgumentException
     * @return string
     */
    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('No Eventstore driver was specified.');
    }
}

