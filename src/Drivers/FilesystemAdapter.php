<?php

declare(strict_types=1);

namespace Core\EventStore\Drivers;

use Core\EventSourcing\Contracts\EventStore;
use Core\EventSourcing\Contracts\Transactional;
use Core\EventStore\Exceptions\NotFoundException;
use Exception;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\LazyCollection;

class FilesystemAdapter implements EventStore, Transactional
{
    /**
     * @var string
     */
    private string $path;

    /**
     * FilesystemAdapter constructor.
     * @param Repository $config
     * @throws Exception
     */
    public function __construct(Repository $config)
    {
        $path = $config->get("eventstore.connections.filesystem.database", null);
        if (!$path) throw new Exception("Undefined config eventstore.connections.filesystem.database");
        $this->path = $path;
    }

    /**
     * @param iterable $events
     * @return bool
     */
    public function commit(iterable $events)
    {
        $eventsArray = is_array($events) ? $events : (is_object($events) && method_exists($events, 'toArray') ? $events->toArray() : iterator_to_array($events));
        
        // Open file for read/write. If it doesn't exist, 'c+' creates it.
        $fp = fopen($this->path, 'c+');
        if (flock($fp, LOCK_EX)) {
            $existingVersions = [];
            rewind($fp);
            while (($line = fgets($fp)) !== false) {
                $row = json_decode($line, true);
                if (is_array($row)) {
                    $id = $row['aggregate_id'] ?? null;
                    $v = $row['aggregate_version'] ?? $row['version'] ?? null;
                    if ($id !== null && $v !== null) {
                        $existingVersions[$id][$v] = true;
                    }
                }
            }

            foreach ($eventsArray as $event) {
                $id = $event['aggregate_id'] ?? null;
                $v = $event['aggregate_version'] ?? $event['version'] ?? null;
                if ($id !== null && $v !== null && isset($existingVersions[$id][$v])) {
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    throw new \Core\EventStore\Exceptions\ConcurrencyException((string)$id);
                }
                if ($id !== null && $v !== null) {
                    $existingVersions[$id][$v] = true;
                }
            }

            fseek($fp, 0, SEEK_END);
            foreach ($eventsArray as $event) {
                fwrite($fp, json_encode($event, JSON_UNESCAPED_UNICODE) . PHP_EOL);
            }
            flock($fp, LOCK_UN);
        } else {
            fclose($fp);
            throw new \RuntimeException("Unable to lock the file for writing.");
        }
        fclose($fp);
        return true;
    }

    public function exists(string $aggregate_id): bool
    {
        $fp = fopen($this->path, 'r');
        while ($row = fgets($fp)) {
            $row = json_decode($row, false);
            if ($row->aggregate_id == $aggregate_id) {
                fclose($fp);
                return true;
            }
        }
        fclose($fp);
        return false;
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


    public function beginTransaction(): void
    {
        // do nothing, as file locking is handled in the commit method
    }

    public function commitTransaction(): void
    {
        // do nothing, as file locking is handled in the commit method
    }

    public function rollbackTransaction(): void
    {
        // do nothing, as file locking is handled in the commit method
    }
}