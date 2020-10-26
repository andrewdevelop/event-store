<?php

namespace Core\EventStore\Drivers;

use Core\EventSourcing\Contracts\EventStore;
use Illuminate\Support\Collection;

class InMemoryAdapter implements EventStore
{
    /**
     * Data store.
     * @var Collection
     */
    protected $store;

    /**
     * InMemoryAdapter constructor.
     */
    public function __construct()
    {
        $this->store = new Collection();
    }

    /**
     * @param iterable $events
     * @return bool|Collection
     */
    public function commit(iterable $events)
    {
        $this->store = $this->store->merge($events);
        return $this->store;
    }

    /**
     * @param string $aggregate_id
     * @param null $version
     * @return array
     */
    public function load($aggregate_id, $version = null)
    {
        return $this->store
            ->filter(function ($e) use ($aggregate_id) {
                return $e['aggregate_id'] == $aggregate_id;
            })
            ->toArray();
    }

    /**
     * @return array
     */
    public function loadAll()
    {
        return $this->store->toArray();
    }
}