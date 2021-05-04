<?php

namespace Core\EventStore\Drivers;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventStore\Exceptions\NotFoundException;
use Exception;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\LazyCollection;

class FilesystemAdapter implements EventStore
{
    /**
     * @var string
     */
    private $path;

    /**
     * FilesystemAdapter constructor.
     * @param Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->path = $config->get("eventstore.connections.filesystem.database", null);
        if (!$this->path) throw new Exception("Undefined config eventstore.connections.filesystem.database");
    }

    /**
     * @param iterable $events
     * @return bool
     */
    public function commit(iterable $events)
    {
        $fp = fopen($this->path, 'a');
        foreach ($events as $event) fwrite($fp, json_encode($event, JSON_UNESCAPED_UNICODE) . PHP_EOL);
        fclose($fp);
        return true;
    }

    /**
     * @param string $aggregate_id
     * @return array|LazyCollection
     * @throws NotFoundException
     */
    public function load($aggregate_id)
    {
        $events = LazyCollection::make(function () {
            $fp = fopen($this->path, 'r');
            while ($row = fgets($fp)) yield json_decode($row, false);
            fclose($fp);
        })->filter(function ($row) use ($aggregate_id) {
            return $row->aggregate_id == $aggregate_id;
        });
        if ($events->count() == 0) throw new NotFoundException($aggregate_id);

        return $events;
    }

    /**
     * @return array|LazyCollection
     */
    public function loadAll()
    {
        return LazyCollection::make(function () {
            $fp = fopen($this->path, 'r');
            while ($row = fgets($fp)) yield json_decode($row, false);
            fclose($fp);
        });
    }
}