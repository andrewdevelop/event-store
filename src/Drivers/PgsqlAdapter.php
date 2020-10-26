<?php

namespace Core\EventStore\Drivers;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventStore\Exceptions\NotFoundException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;

class PgsqlAdapter implements EventStore
{

    /**
     * Main DB table.
     * @var string
     */
    protected $table = 'event_streams';

    /**
     * IoC container instance.
     * @var Container
     */
    protected $container;

    /**
     * The DB connection instance.
     * @var ConnectionInterface
     */
    protected $connection;

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

    /**
     * Initialize database if necessary.
     * @return void
     */
    public function init()
    {
        $builder = $this->connection->getSchemaBuilder();

        if ($builder->hasTable($this->table)) return;

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
        });
    }

    public function commit(iterable $events)
    {
        return $this->connection
            ->table($this->table)
            ->insert($events);
    }

    /**
     * @param string $aggregate_id
     * @param null $version
     * @return LazyCollection
     * @throws NotFoundException
     */
    public function load($aggregate_id, $version = null)
    {
        $events = $this->connection
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
        return $this->connection
            ->table($this->table)
            ->selectRaw('id, name, version, aggregate_id, aggregate_type, aggregate_version, payload, metadata, created_at')
            ->whereNotNull('aggregate_id')
            ->orderBy('created_at', 'ASC')
            ->cursor();
    }

}