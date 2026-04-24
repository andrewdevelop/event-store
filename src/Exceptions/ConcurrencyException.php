<?php

namespace Core\EventStore\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when an optimistic locking conflict is detected during event store commit.
 *
 * This happens when two concurrent processes attempt to write events with the
 * same aggregate_version for the same aggregate_id. The unique constraint on
 * (aggregate_id, aggregate_version) in the event_streams table causes the second
 * write to fail, which is then caught and wrapped in this exception.
 */
class ConcurrencyException extends RuntimeException
{
    public function __construct(string $aggregate_id, int $code = 0, ?Throwable $previous = null)
    {
        $message = "Concurrency error in aggregate '$aggregate_id'.";
        parent::__construct($message, $code, $previous);
    }
}
