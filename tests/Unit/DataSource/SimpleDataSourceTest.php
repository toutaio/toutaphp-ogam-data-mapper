<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\DataSource;

use PDO;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Contract\DataSourceInterface;
use Touta\Ogam\DataSource\SimpleDataSource;

final class SimpleDataSourceTest extends TestCase
{
    public function testImplementsDataSourceInterface(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');

        $this->assertInstanceOf(DataSourceInterface::class, $dataSource);
    }

    public function testGetConnection(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');

        $connection = $dataSource->getConnection();

        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testGetConnectionCreatesNewConnectionEachTime(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');

        $connection1 = $dataSource->getConnection();
        $connection2 = $dataSource->getConnection();

        $this->assertNotSame($connection1, $connection2);
    }

    public function testGetDsn(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');

        $this->assertSame('sqlite::memory:', $dataSource->getDsn());
    }

    public function testGetUsernameDefault(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');

        $this->assertNull($dataSource->getUsername());
    }

    public function testGetUsernameCustom(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:', 'user');

        $this->assertSame('user', $dataSource->getUsername());
    }

    public function testGetOptionsDefault(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');
        $options = $dataSource->getOptions();

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        $this->assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
        $this->assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
    }

    public function testGetOptionsCustom(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
        ]);

        $options = $dataSource->getOptions();

        $this->assertSame(PDO::ERRMODE_SILENT, $options[PDO::ATTR_ERRMODE]);
        $this->assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
    }

    public function testConnectionHasCorrectErrorMode(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');
        $connection = $dataSource->getConnection();

        $this->assertSame(
            PDO::ERRMODE_EXCEPTION,
            $connection->getAttribute(PDO::ATTR_ERRMODE),
        );
    }

    public function testConnectionHasCorrectFetchMode(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');
        $connection = $dataSource->getConnection();

        $this->assertSame(
            PDO::FETCH_ASSOC,
            $connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE),
        );
    }

    public function testWithCredentials(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:', 'testuser', 'testpass');

        $this->assertSame('testuser', $dataSource->getUsername());
        $this->assertInstanceOf(PDO::class, $dataSource->getConnection());
    }
}
