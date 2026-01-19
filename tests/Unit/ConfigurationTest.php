<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\DataSource\SimpleDataSource;
use Touta\Ogam\Executor\ExecutorType;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Transaction\ManagedTransactionFactory;

final class ConfigurationTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testDefaultSettings(): void
    {
        $this->assertTrue($this->configuration->isCacheEnabled());
        $this->assertFalse($this->configuration->isLazyLoadingEnabled());
        $this->assertFalse($this->configuration->isMapUnderscoreToCamelCase());
        $this->assertSame(ExecutorType::SIMPLE, $this->configuration->getDefaultExecutorType());
        $this->assertSame(0, $this->configuration->getDefaultStatementTimeout());
        $this->assertFalse($this->configuration->isUseGeneratedKeys());
        $this->assertSame('default', $this->configuration->getDefaultEnvironment());
        $this->assertFalse($this->configuration->isDebugMode());
    }

    public function testSetSettings(): void
    {
        $this->configuration
            ->setCacheEnabled(false)
            ->setLazyLoadingEnabled(true)
            ->setMapUnderscoreToCamelCase(true)
            ->setDefaultExecutorType(ExecutorType::BATCH)
            ->setDefaultStatementTimeout(30000)
            ->setUseGeneratedKeys(true)
            ->setDefaultEnvironment('production')
            ->setDebugMode(true);

        $this->assertFalse($this->configuration->isCacheEnabled());
        $this->assertTrue($this->configuration->isLazyLoadingEnabled());
        $this->assertTrue($this->configuration->isMapUnderscoreToCamelCase());
        $this->assertSame(ExecutorType::BATCH, $this->configuration->getDefaultExecutorType());
        $this->assertSame(30000, $this->configuration->getDefaultStatementTimeout());
        $this->assertTrue($this->configuration->isUseGeneratedKeys());
        $this->assertSame('production', $this->configuration->getDefaultEnvironment());
        $this->assertTrue($this->configuration->isDebugMode());
    }

    public function testTypeAliases(): void
    {
        // Default aliases
        $this->assertSame('string', $this->configuration->resolveTypeAlias('string'));
        $this->assertSame('int', $this->configuration->resolveTypeAlias('integer'));
        $this->assertSame(\DateTimeInterface::class, $this->configuration->resolveTypeAlias('datetime'));

        // Custom alias
        $this->configuration->addTypeAlias('user', 'App\\Entity\\User');
        $this->assertSame('App\\Entity\\User', $this->configuration->resolveTypeAlias('user'));
        $this->assertSame('App\\Entity\\User', $this->configuration->resolveTypeAlias('USER'));
    }

    public function testEnvironments(): void
    {
        $dataSource = new SimpleDataSource('sqlite::memory:');
        $transactionFactory = new ManagedTransactionFactory();
        $environment = new Environment('test', $dataSource, $transactionFactory);

        $this->configuration->addEnvironment($environment);

        $this->assertSame($environment, $this->configuration->getEnvironment('test'));
        $this->assertNull($this->configuration->getEnvironment('nonexistent'));
    }

    public function testMappedStatements(): void
    {
        $statement = new MappedStatement(
            'findById',
            'UserMapper',
            StatementType::SELECT,
            'SELECT * FROM users WHERE id = #{id}',
        );

        $this->configuration->addMappedStatement($statement);

        $this->assertTrue($this->configuration->hasMappedStatement('UserMapper.findById'));
        $this->assertSame($statement, $this->configuration->getMappedStatement('UserMapper.findById'));
        $this->assertNull($this->configuration->getMappedStatement('Unknown.statement'));
    }

    public function testResultMaps(): void
    {
        $resultMap = new ResultMap(
            'UserMapper.UserResult',
            'App\\Entity\\User',
        );

        $this->configuration->addResultMap($resultMap);

        $this->assertSame($resultMap, $this->configuration->getResultMap('UserMapper.UserResult'));
        $this->assertNull($this->configuration->getResultMap('Unknown.result'));
    }

    public function testMapperInterfaces(): void
    {
        $this->configuration->addMapper('App\\Mapper\\UserMapper');
        $this->configuration->addMapper('App\\Mapper\\PostMapper');
        $this->configuration->addMapper('App\\Mapper\\UserMapper'); // Duplicate

        $this->assertTrue($this->configuration->hasMapper('App\\Mapper\\UserMapper'));
        $this->assertTrue($this->configuration->hasMapper('App\\Mapper\\PostMapper'));
        $this->assertFalse($this->configuration->hasMapper('App\\Mapper\\Unknown'));

        $interfaces = $this->configuration->getMapperInterfaces();
        $this->assertCount(2, $interfaces);
    }
}
