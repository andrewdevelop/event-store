<?php

declare(strict_types=1);

namespace Core\EventStore\Drivers;

use Carbon\Carbon;
use Core\EventSourcing\Contracts\KeyRing;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;

class PgsqlKeyRingAdapter implements KeyRing
{
    /**
     * Keyring DB table.
     * @var string
     */
    protected string $table = 'pii_keys';

    /**
     * The DB connection instance.
     * @var mixed
     */
    protected mixed $connection;

    /**
     * Constructor. Uses the same database as the EventStore PgsqlAdapter.
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
                $table->uuid('pii_id')->primary();
                $table->text('key');
                $table->timestamp('created_at', 6)->nullable();
            });
        }
    }

    /**
     * @param string $pii_id
     * @return string|null
     */
    public function get(string $pii_id): ?string
    {
        $key = $this->getConnection()
            ->table($this->table)
            ->where('pii_id', $pii_id)
            ->value('key');

        return $key !== null ? (string) $key : null;
    }

    /**
     * @param string $pii_id
     * @param string $key
     * @return void
     */
    public function put(string $pii_id, string $key): void
    {
        $this->getConnection()
            ->table($this->table)
            ->updateOrInsert(
                ['pii_id' => $pii_id],
                ['key' => $key, 'created_at' => Carbon::now()]
            );
    }

    /**
     * @param string $pii_id
     * @return void
     */
    public function forget(string $pii_id): void
    {
        $this->getConnection()
            ->table($this->table)
            ->where('pii_id', $pii_id)
            ->delete();
    }
}
