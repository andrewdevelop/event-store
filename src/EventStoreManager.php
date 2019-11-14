<?php 

namespace Core\EventStore;

use Illuminate\Support\Manager;
use InvalidArgumentException;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventStore\Drivers\PostgresqlAdapter;

class EventStoreManager extends Manager
{

    /**
     * Create an instance of the specified driver.
     * @return \Core\EventStore\Drivers\MysqlAdapter
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

