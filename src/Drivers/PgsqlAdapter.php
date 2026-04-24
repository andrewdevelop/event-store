<?php

declare(strict_types=1);

namespace Core\EventStore\Drivers;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventSourcing\Contracts\Transactional;
use Core\EventStore\Exceptions\ConcurrencyException;
use Core\EventStore\Exceptions\NotFoundException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\LazyCollection;

class PgsqlAdapter implements EventStore, Transactional
{
    /**
     * Main DB table.
     * @var string
     */
    protected string $table = 'event_streams';

    /**
     * IoC container instance.
     * @var Container
     */
    protected Container $container;

    /**
     * The DB connection instance.
     * @var mixed
     */
    protected mixed $connection;

    /**
     * Constructor.
     * @param Repository $config
     * @param ConnectionFactory $connections
     */
    public function __construct(Repository $config, ConnectionFactory $connections)
    {
        $connect_config = $config->get("eventstore.connections.pgsql");
        $this->connection = $connections->make($connect_config, 'pgsql');
    }

    protected function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Initialize database if necessary.
     * @return void
     */
    public function init(): void
    {
        $builder = $this->getConnection()->getSchemaBuilder();

        if (!$builder->hasTable($this->table)) {
            $builder->create($this->table, function ($table) {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->integer('version')->default(0);
                $table->uuid('aggregate_id');
                $table->string('aggregate_type', 255);
                $table->bigInteger('aggregate_version')->default(0);
                $table->jsonb('payload')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamp('created_at', 6);
                // Unique constraint to prevent concurrent writes with same aggregate_version
                $table->unique(['aggregate_id', 'aggregate_version']);
                // Indexes for faster queries
                $table->index(['aggregate_id', 'created_at'], 'idx_aggregate_created');
                $table->index(['created_at'], 'idx_created');
            });
        }
    }

    /**
     * @param iterable $events
     * @return bool
     * @throws ConcurrencyException
     */
    public function commit(iterable $events)
    {
        $eventsArray = (array)$events;
        try {
            return $this->getConnection()
                ->table($this->table)
                ->insert($eventsArray);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                $aggregateId = isset($eventsArray[0]['aggregate_id']) ? (string)$eventsArray[0]['aggregate_id'] : 'unknown';
                throw new ConcurrencyException($aggregateId, 23505, $e);
            }
            throw $e;
        }
    }

    /**
     * @param string $aggregate_id
     * @return bool
     */
    public function exists(string $aggregate_id): bool
    {
        return $this->getConnection()
            ->table($this->table)
            ->where('aggregate_id', $aggregate_id)
            ->exists();
    }

    /**
     * @param string $aggregate_id
     * @param null $version
     * @return LazyCollection
     * @throws NotFoundException
     */
    public function load($aggregate_id, $version = null)
    {
        $events = $this->getConnection()
            ->table($this->table)
            ->selectRaw('id, name, version, aggregate_id, aggregate_type, aggregate_version, payload, metadata, created_at')
            ->where('aggregate_id', $aggregate_id)
            ->orderBy('created_at', 'ASC')
            ->when($version !== null, function (Builder $q) use ($version) {
                $q->where('aggregate_version', '<=', $version);
            })
            ->cursor();

        if ($events->count() == 0) throw new NotFoundException($aggregate_id);

        return $events;
    }

    /**
     * @return LazyCollection
     */
    public function loadAll()
    {
        return $this->getConnection()
            ->table($this->table)
            ->selectRaw('id, name, version, aggregate_id, aggregate_type, aggregate_version, payload, metadata, created_at')
            ->whereNotNull('aggregate_id')
            ->orderBy('created_at', 'ASC')
            ->cursor();
    }

    public function beginTransaction(): void
    {
        $this->getConnection()->beginTransaction();
    }

    public function commitTransaction(): void
    {
        $this->getConnection()->commit();
    }

    public function rollbackTransaction(): void
    {
        $this->getConnection()->rollBack();
    }
}