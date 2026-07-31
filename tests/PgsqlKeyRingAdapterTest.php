<?php

namespace Core\EventStore\Tests;

use Core\EventStore\Drivers\PgsqlKeyRingAdapter;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\PostgresBuilder;
use Mockery;

class PgsqlKeyRingAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_init_creates_table_if_not_exists()
    {
        $config = $this->config;
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $schemaBuilder = Mockery::mock(PostgresBuilder::class);

        $connectionFactory->shouldReceive('make')->once()->andReturn($connection);
        $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schemaBuilder);
        $schemaBuilder->shouldReceive('hasTable')->with('pii_keys')->once()->andReturn(false);
        $schemaBuilder->shouldReceive('create')
            ->with('pii_keys', Mockery::type('Closure'))
            ->once()
            ->andReturnUsing(function ($table, $closure) {
                $blueprint = Mockery::mock(Blueprint::class)->makePartial();
                $blueprint->shouldReceive('uuid')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('text')->andReturn(new \Illuminate\Support\Fluent());
                $blueprint->shouldReceive('timestamp')->andReturn(new \Illuminate\Support\Fluent());

                $closure($blueprint);
            });

        $adapter = new PgsqlKeyRingAdapter($config, $connectionFactory);
        $adapter->init();

        $this->assertTrue(true);
    }

    public function test_init_does_not_create_table_if_exists()
    {
        $config = $this->config;
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $schemaBuilder = Mockery::mock(PostgresBuilder::class);

        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);
        $schemaBuilder->shouldReceive('hasTable')->with('pii_keys')->andReturn(true);
        $schemaBuilder->shouldNotReceive('create');

        $adapter = new PgsqlKeyRingAdapter($config, $connectionFactory);
        $adapter->init();

        $this->assertTrue(true);
    }

    public function test_get_returns_key_for_subject()
    {
        $config = $this->config;
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);

        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('pii_keys')->andReturn($builder);
        $builder->shouldReceive('where')->with('pii_id', 'subject-1')->andReturn($builder);
        $builder->shouldReceive('value')->with('key')->andReturn('secret-key');

        $adapter = new PgsqlKeyRingAdapter($config, $connectionFactory);
        $this->assertSame('secret-key', $adapter->get('subject-1'));
    }

    public function test_get_returns_null_for_unknown_or_forgotten_subject()
    {
        $config = $this->config;
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);

        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('pii_keys')->andReturn($builder);
        $builder->shouldReceive('where')->with('pii_id', 'subject-1')->andReturn($builder);
        $builder->shouldReceive('value')->with('key')->andReturnNull();

        $adapter = new PgsqlKeyRingAdapter($config, $connectionFactory);
        $this->assertNull($adapter->get('subject-1'));
    }

    public function test_put_stores_key_for_subject()
    {
        $config = $this->config;
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);

        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('pii_keys')->andReturn($builder);
        $builder->shouldReceive('updateOrInsert')
            ->once()
            ->withArgs(function ($attributes, $values) {
                return $attributes === ['pii_id' => 'subject-1']
                    && isset($values['key']) && $values['key'] === 'secret-key';
            })
            ->andReturn(true);

        $adapter = new PgsqlKeyRingAdapter($config, $connectionFactory);
        $adapter->put('subject-1', 'secret-key');

        $this->assertTrue(true);
    }

    public function test_forget_deletes_key_for_subject()
    {
        $config = $this->config;
        $connectionFactory = Mockery::mock(ConnectionFactory::class);
        $connection = Mockery::mock(Connection::class);
        $builder = Mockery::mock(Builder::class);

        $connectionFactory->shouldReceive('make')->andReturn($connection);
        $connection->shouldReceive('table')->with('pii_keys')->andReturn($builder);
        $builder->shouldReceive('where')->with('pii_id', 'subject-1')->andReturn($builder);
        $builder->shouldReceive('delete')->once()->andReturn(1);

        $adapter = new PgsqlKeyRingAdapter($config, $connectionFactory);
        $adapter->forget('subject-1');

        $this->assertTrue(true);
    }
}
