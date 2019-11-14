<?php 

namespace Core\EventStore\Drivers;

use Core\Contracts\Event;
use Core\EventSourcing\Contracts\EventStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Core\EventStore\Exceptions\NotFoundException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;

class PostgresqlAdapter implements EventStore
{

	/**
	 * Main DB table.
	 * @var string
	 */
	protected $table = 'event_streams';

	/**
	 * IoC container instance.
	 * @var \Illuminate\Contracts\Container\Container
	 */
	protected $container;

	/**
	 * The DB connection instance.
	 * @var \Illuminate\Database\ConnectionInterface
	 */
	protected $connection;

	/**
	 * Constructor.
	 * @param PhpOrient $query 
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * ConnectionInterface
	 * @param  \Illuminate\Database\ConnectionInterface $connection
	 * @return self
	 */
	public function connection($connection)
	{
		$this->connection = $connection;
		return $this;
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


	public function load($aggregate_id) 
	{
		$query = 'id, name, version, aggregate_id, aggregate_type, aggregate_version, payload, metadata, DATE_FORMAT(created_at, \'%Y-%m-%d %H:%i:%s.%f\') as created_at';

		$events = $this->connection
			->table($this->table)
			->selectRaw($query)
            ->where('aggregate_id', $aggregate_id)
			->orderBy('created_at', 'ASC')
			->get();
		
		if (!$events) {
			throw new NotFoundException($aggregate_id);
		}

		return $events;	
	}


	public function loadAll()
	{
		$query = 'id, name, version, aggregate_id, aggregate_type, aggregate_version, payload, metadata, DATE_FORMAT(created_at, \'%Y-%m-%d %H:%i:%s.%f\') as created_at';

		$events = $this->connection
			->table($this->table)
			->selectRaw($query)
            ->where('aggregate_id', $aggregate_id)
			->orderBy('created_at', 'ASC')
			->get();

		return $events;
	}

}