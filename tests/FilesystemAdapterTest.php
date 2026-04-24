<?php

namespace Core\EventStore\Tests;

use Core\EventStore\Drivers\FilesystemAdapter;
use Core\EventStore\Exceptions\NotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\LazyCollection;

class FilesystemAdapterTest extends TestCase
{
    protected string $testFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testFilePath = __DIR__ . '/test_events.json';
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
        
        Config::set('eventstore.connections.filesystem.database', $this->testFilePath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
        parent::tearDown();
    }

    public function test_it_throws_exception_if_no_path_configured()
    {
        Config::set('eventstore.connections.filesystem.database', null);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Undefined config eventstore.connections.filesystem.database");
        
        new FilesystemAdapter(app('config'));
    }

    public function test_it_commits_events_to_file()
    {
        $adapter = new FilesystemAdapter(app('config'));
        
        $events = [
            ['aggregate_id' => '1', 'type' => 'A'],
            ['aggregate_id' => '2', 'type' => 'B'],
        ];
        
        $adapter->commit($events);
        
        $this->assertFileExists($this->testFilePath);
        
        $contents = file($this->testFilePath);
        $this->assertCount(2, $contents);
        $this->assertStringContainsString('{"aggregate_id":"1","type":"A"}', $contents[0]);
    }

    public function test_it_loads_events_by_aggregate_id()
    {
        $adapter = new FilesystemAdapter(app('config'));
        
        $events = [
            ['aggregate_id' => '1', 'type' => 'A'],
            ['aggregate_id' => '2', 'type' => 'B'],
            ['aggregate_id' => '1', 'type' => 'C'],
        ];
        
        $adapter->commit($events);
        
        $loaded = $adapter->load('1');
        
        $this->assertInstanceOf(LazyCollection::class, $loaded);
        $array = $loaded->values()->toArray();
        $this->assertCount(2, $array);
        $this->assertEquals('1', $array[0]->aggregate_id);
        $this->assertEquals('A', $array[0]->type);
        $this->assertEquals('1', $array[1]->aggregate_id);
        $this->assertEquals('C', $array[1]->type);
    }

    public function test_it_throws_not_found_exception_when_aggregate_id_not_found()
    {
        $adapter = new FilesystemAdapter(app('config'));
        
        $adapter->commit([
            ['aggregate_id' => '1', 'type' => 'A']
        ]);
        
        $this->expectException(NotFoundException::class);
        $adapter->load('2');
    }

    public function test_it_loads_all_events()
    {
        $adapter = new FilesystemAdapter(app('config'));
        
        $events = [
            ['aggregate_id' => '1', 'type' => 'A', 'version' => 1],
            ['aggregate_id' => '2', 'type' => 'B', 'version' => 1],
        ];
        
        $adapter->commit($events);
        
        $loaded = $adapter->loadAll();
        
        $this->assertInstanceOf(LazyCollection::class, $loaded);
        $array = $loaded->values()->toArray();
        $this->assertCount(2, $array);
    }

    public function test_it_throws_concurrency_exception_on_duplicate_version()
    {
        $adapter = new FilesystemAdapter(app('config'));
        
        $adapter->commit([
            ['aggregate_id' => '1', 'type' => 'A', 'version' => 1]
        ]);
        
        $this->expectException(\Core\EventStore\Exceptions\ConcurrencyException::class);
        $this->expectExceptionMessage("Concurrency error in aggregate '1'.");

        $adapter->commit([
            ['aggregate_id' => '1', 'type' => 'B', 'version' => 1]
        ]);
    }
}
