<?php 

namespace Core\EventStore\Drivers;

use Core\Contracts\Event;
use Core\EventSourcing\Contracts\EventStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Core\EventStore\Exceptions\NotFoundException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

class InMemoryAdapter implements EventStore
{

	/**
	 * IoC container instance.
	 * @var \Illuminate\Contracts\Container\Container
	 */
	protected $container;

	/**
	 * Data store.
	 * @var \Illuminate\Support\Collection
	 */
	protected $store;

	/**
	 * Constructor.
	 * @param PhpOrient $query 
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
		$this->store = new Collection();
	}

	/**
	 * ConnectionInterface
	 * @param  \Illuminate\Database\ConnectionInterface $connection
	 * @return self
	 */
	public function connection($connection)
	{
		return $this;
	}

	/**
	 * Initialize database if necessary.
	 * @return void
	 */
	public function init()
	{
		return true;
	}

	
	public function commit(iterable $events) 
	{
		return $this->store->merge($events);
	}


	public function load($aggregate_id, $version = null) 
	{
		return $this->store
			->filter(function($e) use ($aggregate_id) {
				return (array) ($e['aggregate_id']) == $aggregate_id;
			})
			->toArray();	
	}


	public function loadAll()
	{
		return $this->store->toArray();
	}

}