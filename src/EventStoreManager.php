<?php

namespace Core\EventStore;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventStore\Drivers\FilesystemAdapter;
use Core\EventStore\Drivers\InMemoryAdapter;
use Core\EventStore\Drivers\PgsqlAdapter;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class EventStoreManager extends Manager
{
    /**
     * Get the default driver name.
     * @return string
     * @throws InvalidArgumentException
     */
    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('No Eventstore driver was specified.');
    }

    /**
     * Build a driver instance.
     * @param string $class
     * @return EventStore
     */
    protected function resolve($class)
    {
        return $this->container->make($class, ['config' => $this->config]);
    }

    /**
     * Create an instance of the specified driver.
     * @return EventStore
     */
    protected function createInMemoryDriver()
    {
        return $this->resolve(InMemoryAdapter::class);
    }

    /**
     * Create an instance of the specified driver.
     * @return EventStore
     */
    protected function createFilesystemDriver()
    {
        return $this->resolve(FilesystemAdapter::class);
    }

    /**
     * Create an instance of the specified driver.
     * @return EventStore
     */
    protected function createPgsqlDriver()
    {
        return $this->resolve(PgsqlAdapter::class);
    }
}

