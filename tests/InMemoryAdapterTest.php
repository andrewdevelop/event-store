<?php

namespace Core\EventStore\Tests;

use Core\EventStore\Drivers\InMemoryAdapter;

class InMemoryAdapterTest extends TestCase
{
    public function test_it_commits_and_loads_events()
    {
        $adapter = new InMemoryAdapter();

        $events = [
            ['aggregate_id' => '123', 'event' => 'UserCreated', 'version' => 1],
            ['aggregate_id' => '123', 'event' => 'UserUpdated', 'version' => 2],
            ['aggregate_id' => '456', 'event' => 'PostCreated', 'version' => 1],
        ];

        $result = $adapter->commit($events);

        $this->assertTrue($result);

        $loaded123 = array_values($adapter->load('123'));
        $this->assertCount(2, $loaded123);
        $this->assertEquals('UserCreated', $loaded123[0]['event']);
        $this->assertEquals('UserUpdated', $loaded123[1]['event']);

        $loaded456 = array_values($adapter->load('456'));
        $this->assertCount(1, $loaded456);
        $this->assertEquals('PostCreated', $loaded456[0]['event']);
    }

    public function test_it_loads_all_events()
    {
        $adapter = new InMemoryAdapter();

        $events = [
            ['aggregate_id' => '123', 'event' => 'UserCreated', 'version' => 1],
            ['aggregate_id' => '456', 'event' => 'PostCreated', 'version' => 1],
        ];

        $adapter->commit($events);

        $all = $adapter->loadAll();
        $this->assertCount(2, $all);
    }

    public function test_it_throws_concurrency_exception_on_duplicate_version()
    {
        $adapter = new InMemoryAdapter();

        $adapter->commit([
            ['aggregate_id' => '123', 'event' => 'UserCreated', 'version' => 1]
        ]);

        $this->expectException(\Core\EventStore\Exceptions\ConcurrencyException::class);
        $this->expectExceptionMessage("Concurrency error in aggregate '123'.");

        $adapter->commit([
            ['aggregate_id' => '123', 'event' => 'UserUpdated', 'version' => 1]
        ]);
    }
}
