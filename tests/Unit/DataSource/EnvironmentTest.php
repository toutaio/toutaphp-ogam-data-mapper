<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\DataSource;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Contract\DataSourceInterface;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\DataSource\SimpleDataSource;
use Touta\Ogam\Transaction\PdoTransactionFactory;
use Touta\Ogam\Transaction\TransactionFactory;

final class EnvironmentTest extends TestCase
{
    public function testGetId(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $environment = new Environment('development', $dataSource, $transactionFactory);

        $this->assertSame('development', $environment->getId());
    }

    public function testGetDataSource(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $environment = new Environment('production', $dataSource, $transactionFactory);

        $this->assertSame($dataSource, $environment->getDataSource());
    }

    public function testGetTransactionFactory(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $environment = new Environment('test', $dataSource, $transactionFactory);

        $this->assertSame($transactionFactory, $environment->getTransactionFactory());
    }

    public function testConstructorWithRealImplementations(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');
        $transactionFactory = new PdoTransactionFactory();

        $environment = new Environment('integration', $dataSource, $transactionFactory);

        $this->assertEquals('integration', $environment->getId());
        $this->assertInstanceOf(SimpleDataSource::class, $environment->getDataSource());
        $this->assertInstanceOf(PdoTransactionFactory::class, $environment->getTransactionFactory());
    }

    public function testMultipleEnvironments(): void
    {
        $devDataSource = $this->createMock(DataSourceInterface::class);
        $devTransactionFactory = $this->createMock(TransactionFactory::class);

        $prodDataSource = $this->createMock(DataSourceInterface::class);
        $prodTransactionFactory = $this->createMock(TransactionFactory::class);

        $devEnv = new Environment('development', $devDataSource, $devTransactionFactory);
        $prodEnv = new Environment('production', $prodDataSource, $prodTransactionFactory);

        $this->assertEquals('development', $devEnv->getId());
        $this->assertEquals('production', $prodEnv->getId());
        $this->assertNotSame($devEnv->getDataSource(), $prodEnv->getDataSource());
        $this->assertNotSame($devEnv->getTransactionFactory(), $prodEnv->getTransactionFactory());
    }
}
