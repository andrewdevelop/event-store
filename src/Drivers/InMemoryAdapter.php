<?php

declare(strict_types=1);

namespace Core\EventStore\Drivers;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventSourcing\Contracts\Transactional;
use Core\EventStore\Exceptions\ConcurrencyException;
use Illuminate\Support\Collection;

class InMemoryAdapter implements EventStore, Transactional
{
    /**
     * Data store.
     * @var Collection
     */
    protected Collection $store;

    /**
     * InMemoryAdapter constructor.
     */
    public function __construct()
    {
        $this->store = new Collection();
    }

    /**
     * @param iterable $events
     * @return bool
     * @throws ConcurrencyException
     */
    public function commit(iterable $events)
    {
        $eventsArray = is_array($events) ? $events : (is_object($events) && method_exists($events, 'toArray') ? $events->toArray() : iterator_to_array($events));
        
        $existing = [];
        foreach ($this->store as $e) {
            $id = $e['aggregate_id'] ?? null;
            $v = $e['aggregate_version'] ?? $e['version'] ?? null;
            if ($id !== null && $v !== null) {
                $existing[$id][$v] = true;
            }
        }

        foreach ($eventsArray as $event) {
            $id = $event['aggregate_id'] ?? null;
            $v = $event['aggregate_version'] ?? $event['version'] ?? null;
            if ($id !== null && $v !== null && isset($existing[$id][$v])) {
                throw new ConcurrencyException((string)$id);
            }
            if ($id !== null && $v !== null) {
                $existing[$id][$v] = true;
            }
        }

        $this->store = $this->store->merge($eventsArray);
        return true;
    }

    /**
     * @param string $aggregate_id
     * @return bool
     */
    public function exists(string $aggregate_id): bool
    {
        return $this->store->contains(function ($e) use ($aggregate_id) {
            return $e['aggregate_id'] == $aggregate_id;
        });
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

    public function beginTransaction(): void
    {
        // no-op for in-memory store, as it doesn't support transactions
    }

    public function commitTransaction(): void
    {
        // no-op for in-memory store, as it doesn't support transactions
    }

    public function rollbackTransaction(): void
    {
        // no-op for in-memory store, as it doesn't support transactions
    }
}