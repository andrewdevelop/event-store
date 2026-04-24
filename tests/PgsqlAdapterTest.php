<?php

namespace Core\EventStore\Tests;

use Core\EventStore\Drivers\PgsqlAdapter;
use Core\EventStore\Exceptions\ConcurrencyException;
use Core\EventStore\Exceptions\NotFoundException;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\PostgresBuilder;
use Illuminate\Support\LazyCollection;
use Mockery;

class PgsqlAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_init_creates_table_if_not_exists()
    {
        $config = app('config');
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $schemaBuilder = Mockery::mock(PostgresBuilder::class);
        
        $connectionFactory->shouldReceive('make')
            ->once()
            ->andReturn($connection);
            
        $connection->shouldReceive('getSchemaBuilder')
            ->once()
            ->andReturn($schemaBuilder);
            
        $schemaBuilder->shouldReceive('hasTable')
            ->with('event_streams')
            ->once()
            ->andReturn(false);
            
        $schemaBuilder->shouldReceive('create')
            ->with('event_streams', Mockery::type('Closure'))
            ->once()
            ->andReturnUsing(function($table, $closure) {
                $blueprint = Mockery::mock(Blueprint::class)->makePartial();
                $blueprint->shouldReceive('uuid')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('string')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('integer')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('bigInteger')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('jsonb')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('timestamp')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('unique')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('index')->andReturn(new \Illuminate\Support\Fluent());
                
                $closure($blueprint);
            });
            
        $adapter = new PgsqlAdapter($config, $connectionFactory);
        $adapter->init();
        
        $this->assertTrue(true);
    }

    public function test_init_does_not_create_table_if_exists()
    {
        $config = app('config');
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $schemaBuilder = Mockery::mock(PostgresBuilder::class);
        
        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);
        
        $schemaBuilder->shouldReceive('hasTable')->with('event_streams')->andReturn(true);
        $schemaBuilder->shouldNotReceive('create');
            
        $adapter = new PgsqlAdapter($config, $connectionFactory);
        $adapter->init();
        
        $this->assertTrue(true);
    }

    public function test_commit_inserts_events()
    {
        $config = app('config');
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);
        
        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('event_streams')->andReturn($builder);
        
        $events = [
            ['id' => '1', 'aggregate_id' => '1'],
            ['id' => '2', 'aggregate_id' => '1'],
        ];
        
        $builder->shouldReceive('insert')->with($events)->once()->andReturn(true);
        
        $adapter = new PgsqlAdapter($config, $connectionFactory);
        $result = $adapter->commit($events);
        
        $this->assertTrue($result);
    }

    public function test_commit_throws_concurrency_exception_on_duplicate_aggregate_version()
    {
        $config = app('config');
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);
        
        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('event_streams')->andReturn($builder);
        
        $events = [
            ['id' => '1', 'aggregate_id' => '1'],
        ];
        
        $pdoException = new \PDOException('Error');
        $prop = new \ReflectionProperty(\Exception::class, 'code');
        $prop->setAccessible(true);
        $prop->setValue($pdoException, '23505');
        
        $exception = new QueryException('test connection', 'sql', [], $pdoException);
        
        $builder->shouldReceive('insert')->with($events)->once()->andThrow($exception);
        
        $adapter = new PgsqlAdapter($config, $connectionFactory);
        
        $this->expectException(ConcurrencyException::class);
        $adapter->commit($events);
    }

    public function test_exists_returns_boolean()
    {
        $config = app('config');
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);
        
        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('event_streams')->andReturn($builder);
        $builder->shouldReceive('where')->with('aggregate_id', '123')->andReturn($builder);
        $builder->shouldReceive('exists')->andReturn(true);
        
        $adapter = new PgsqlAdapter($config, $connectionFactory);
        $this->assertTrue($adapter->exists('123'));
    }

    public function test_load_returns_lazy_collection_of_events()
    {
        $config = app('config');
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);
        
        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('event_streams')->andReturn($builder);
        
        $builder->shouldReceive('selectRaw')->andReturn($builder);
        $builder->shouldReceive('where')->with('aggregate_id', '123')->andReturn($builder);
        $builder->shouldReceive('orderBy')->with('created_at', 'ASC')->andReturn($builder);
        $builder->shouldReceive('when')->andReturn($builder);
        
        $events = new LazyCollection([
            (object)['id' => '1', 'aggregate_id' => '123'],
            (object)['id' => '2', 'aggregate_id' => '123'],
        ]);
        $builder->shouldReceive('cursor')->andReturn($events);
        
        $adapter = new PgsqlAdapter($config, $connectionFactory);
        
        $result = $adapter->load('123');
        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertCount(2, $result);
    }

    public function test_load_throws_not_found_exception()
    {
        $config = app('config');
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);
        
        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('event_streams')->andReturn($builder);
        
        $builder->shouldReceive('selectRaw')->andReturn($builder);
        $builder->shouldReceive('where')->with('aggregate_id', '123')->andReturn($builder);
        $builder->shouldReceive('orderBy')->with('created_at', 'ASC')->andReturn($builder);
        $builder->shouldReceive('when')->andReturn($builder);
        
        $events = new LazyCollection([]);
        $builder->shouldReceive('cursor')->andReturn($events);
        
        $adapter = new PgsqlAdapter($config, $connectionFactory);
        
        $this->expectException(NotFoundException::class);
        $adapter->load('123');
    }

    public function test_load_all_returns_all_events()
    {
        $config = app('config');
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);
        
        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('event_streams')->andReturn($builder);
        
        $builder->shouldReceive('selectRaw')->andReturn($builder);
        $builder->shouldReceive('whereNotNull')->with('aggregate_id')->andReturn($builder);
        $builder->shouldReceive('orderBy')->with('created_at', 'ASC')->andReturn($builder);
        
        $events = new LazyCollection([
            (object)['id' => '1', 'aggregate_id' => '123'],
            (object)['id' => '2', 'aggregate_id' => '456'],
        ]);
        $builder->shouldReceive('cursor')->andReturn($events);
        
        $adapter = new PgsqlAdapter($config, $connectionFactory);
        
        $result = $adapter->loadAll();
        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertCount(2, $result);
    }
}
