<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\DataSource;

use PDO;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Contract\DataSourceInterface;
use Touta\Ogam\DataSource\UnpooledDataSource;

final class UnpooledDataSourceTest extends TestCase
{
    public function testImplementsDataSourceInterface(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:');

        $this->assertInstanceOf(DataSourceInterface::class, $dataSource);
    }

    public function testGetConnection(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:');

        $connection = $dataSource->getConnection();

        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testGetConnectionCreatesNewConnectionEachTime(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:');

        $connection1 = $dataSource->getConnection();
        $connection2 = $dataSource->getConnection();

        $this->assertNotSame($connection1, $connection2);
    }

    public function testGetConnectionCountInitiallyZero(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:');

        $this->assertSame(0, $dataSource->getConnectionCount());
    }

    public function testGetConnectionCountIncrementsOnConnection(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:');

        $dataSource->getConnection();
        $this->assertSame(1, $dataSource->getConnectionCount());

        $dataSource->getConnection();
        $this->assertSame(2, $dataSource->getConnectionCount());

        $dataSource->getConnection();
        $this->assertSame(3, $dataSource->getConnectionCount());
    }

    public function testResetConnectionCount(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:');

        $dataSource->getConnection();
        $dataSource->getConnection();
        $this->assertSame(2, $dataSource->getConnectionCount());

        $dataSource->resetConnectionCount();
        $this->assertSame(0, $dataSource->getConnectionCount());
    }

    public function testResetConnectionCountDoesNotAffectNewConnections(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:');

        $dataSource->getConnection();
        $dataSource->resetConnectionCount();
        $dataSource->getConnection();

        $this->assertSame(1, $dataSource->getConnectionCount());
    }

    public function testDefaultOptions(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:');
        $connection = $dataSource->getConnection();

        $this->assertSame(
            PDO::ERRMODE_EXCEPTION,
            $connection->getAttribute(PDO::ATTR_ERRMODE),
        );
        $this->assertSame(
            PDO::FETCH_ASSOC,
            $connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE),
        );
    }

    public function testCustomOptions(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
        ]);

        $connection = $dataSource->getConnection();

        $this->assertSame(
            PDO::ERRMODE_WARNING,
            $connection->getAttribute(PDO::ATTR_ERRMODE),
        );
    }

    public function testWithCredentials(): void
    {
        $dataSource = new UnpooledDataSource('sqlite::memory:', 'testuser', 'testpass');

        $this->assertInstanceOf(PDO::class, $dataSource->getConnection());
        $this->assertSame(1, $dataSource->getConnectionCount());
    }
}
