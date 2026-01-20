<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\DataSource;

use PDO;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Contract\DataSourceInterface;
use Touta\Ogam\DataSource\PooledDataSource;

final class PooledDataSourceTest extends TestCase
{
    public function testImplementsDataSourceInterface(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $this->assertInstanceOf(DataSourceInterface::class, $dataSource);
    }

    public function testGetConnectionReturnsPDO(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $connection = $dataSource->getConnection();

        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testGetConnectionCreatesNewConnectionWhenPoolIsEmpty(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $this->assertEquals(0, $dataSource->getPoolSize());
        $this->assertEquals(0, $dataSource->getTotalConnections());

        $connection = $dataSource->getConnection();

        $this->assertInstanceOf(PDO::class, $connection);
        $this->assertEquals(1, $dataSource->getTotalConnections());
    }

    public function testReleaseConnectionAddsToPool(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $connection = $dataSource->getConnection();
        $dataSource->releaseConnection($connection);

        $this->assertEquals(1, $dataSource->getPoolSize());
    }

    public function testGetConnectionReusesPooledConnection(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $connection1 = $dataSource->getConnection();
        $dataSource->releaseConnection($connection1);

        $connection2 = $dataSource->getConnection();

        $this->assertSame($connection1, $connection2);
        $this->assertEquals(0, $dataSource->getPoolSize());
        $this->assertEquals(1, $dataSource->getTotalConnections());
    }

    public function testPoolRespectMaxSize(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:', maxSize: 2);

        $conn1 = $dataSource->getConnection();
        $conn2 = $dataSource->getConnection();
        $conn3 = $dataSource->getConnection();

        $dataSource->releaseConnection($conn1);
        $dataSource->releaseConnection($conn2);
        $dataSource->releaseConnection($conn3);

        $this->assertEquals(2, $dataSource->getPoolSize());
    }

    public function testReleaseConnectionRollsBackTransaction(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $connection = $dataSource->getConnection();
        $connection->beginTransaction();

        $this->assertTrue($connection->inTransaction());

        $dataSource->releaseConnection($connection);

        $this->assertFalse($connection->inTransaction());
    }

    public function testClearRemovesAllPooledConnections(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $conn1 = $dataSource->getConnection();
        $conn2 = $dataSource->getConnection();

        $dataSource->releaseConnection($conn1);
        $dataSource->releaseConnection($conn2);

        $this->assertEquals(2, $dataSource->getPoolSize());

        $dataSource->clear();

        $this->assertEquals(0, $dataSource->getPoolSize());
    }

    public function testMultipleGetConnectionsIncreasesTotalCount(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $dataSource->getConnection();
        $dataSource->getConnection();
        $dataSource->getConnection();

        $this->assertEquals(3, $dataSource->getTotalConnections());
    }

    public function testConstructorWithOptions(): void
    {
        $dataSource = new PooledDataSource(
            'sqlite::memory:',
            options: [
                PDO::ATTR_TIMEOUT => 10,
            ]
        );

        $connection = $dataSource->getConnection();

        $this->assertInstanceOf(PDO::class, $connection);
        // SQLite may not support all PDO attributes, just verify connection works
    }

    public function testConstructorSetsDefaultOptions(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $connection = $dataSource->getConnection();

        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $connection->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertEquals(PDO::FETCH_ASSOC, $connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
        // SQLite doesn't support ATTR_EMULATE_PREPARES attribute
    }

    public function testReleaseConnectionWithoutTransaction(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $connection = $dataSource->getConnection();

        $this->assertFalse($connection->inTransaction());

        $dataSource->releaseConnection($connection);

        $this->assertEquals(1, $dataSource->getPoolSize());
    }

    public function testPoolLifeCycle(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:', maxSize: 3);

        $conn1 = $dataSource->getConnection();
        $conn2 = $dataSource->getConnection();

        $this->assertEquals(2, $dataSource->getTotalConnections());
        $this->assertEquals(0, $dataSource->getPoolSize());

        $dataSource->releaseConnection($conn1);

        $this->assertEquals(2, $dataSource->getTotalConnections());
        $this->assertEquals(1, $dataSource->getPoolSize());

        $conn3 = $dataSource->getConnection();

        $this->assertSame($conn1, $conn3);
        $this->assertEquals(2, $dataSource->getTotalConnections());
        $this->assertEquals(0, $dataSource->getPoolSize());

        $dataSource->releaseConnection($conn2);
        $dataSource->releaseConnection($conn3);

        $this->assertEquals(2, $dataSource->getPoolSize());
    }

    public function testGetConnectionWithCredentials(): void
    {
        $dataSource = new PooledDataSource(
            'sqlite::memory:',
            username: null,
            password: null
        );

        $connection = $dataSource->getConnection();

        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testMaxSizeDefaultValue(): void
    {
        $dataSource = new PooledDataSource('sqlite::memory:');

        $connections = [];
        for ($i = 0; $i < 15; $i++) {
            $connections[] = $dataSource->getConnection();
        }
        
        foreach ($connections as $conn) {
            $dataSource->releaseConnection($conn);
        }

        $this->assertEquals(10, $dataSource->getPoolSize());
    }
}
