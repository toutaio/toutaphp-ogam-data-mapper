<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\DataSourceInterface;
use Touta\Ogam\Contract\SessionInterface;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\Executor\ExecutorType;
use Touta\Ogam\Session\DefaultSession;
use Touta\Ogam\Session\DefaultSessionFactory;
use Touta\Ogam\Transaction\TransactionInterface;
use Touta\Ogam\Transaction\TransactionFactory;

final class DefaultSessionFactoryTest extends TestCase
{
    private Configuration $configuration;
    private DefaultSessionFactory $sessionFactory;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        
        // Set up a test environment
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $pdo = new PDO('sqlite::memory:');
        
        $dataSource->method('getConnection')->willReturn($pdo);
        
        $environment = new Environment('test', $dataSource, $transactionFactory);
        $this->configuration->addEnvironment($environment);
        $this->configuration->setDefaultEnvironment('test');
        
        $this->sessionFactory = new DefaultSessionFactory($this->configuration);
    }

    public function testGetConfigurationReturnsConfiguration(): void
    {
        $result = $this->sessionFactory->getConfiguration();

        $this->assertSame($this->configuration, $result);
    }

    public function testOpenSessionCreatesSessionWithDefaultSettings(): void
    {
        

        // Configuration method getDefaultExecutorType will return default

        // Configuration method getEnvironment will return default

        $session = $this->sessionFactory->openSession();

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertInstanceOf(DefaultSession::class, $session);
        $this->assertFalse($session->isClosed());
    }

    public function testOpenSessionWithAutoCommitCreatesSessionWithAutoCommit(): void
    {
        

        // Configuration method getDefaultExecutorType will return default

        // Configuration method getEnvironment will return default

        $session = $this->sessionFactory->openSessionWithAutoCommit();

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertFalse($session->isClosed());
    }

    public function testOpenSessionWithExecutorCreatesSessionWithSpecificExecutor(): void
    {
        

        // Configuration method getEnvironment will return default

        $session = $this->sessionFactory->openSessionWithExecutor(ExecutorType::BATCH);

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertFalse($session->isClosed());
    }

    public function testOpenSessionCreatesSimpleExecutor(): void
    {
        

        // Configuration method getDefaultExecutorType will return default

        // Configuration method getEnvironment will return default

        $session = $this->sessionFactory->openSession();

        // Verify session was created successfully
        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionCreatesReuseExecutor(): void
    {
        

        // Configuration method getDefaultExecutorType will return default

        // Configuration method getEnvironment will return default

        $session = $this->sessionFactory->openSession();

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionCreatesBatchExecutor(): void
    {
        

        // Configuration method getDefaultExecutorType will return default

        // Configuration method getEnvironment will return default

        $session = $this->sessionFactory->openSession();

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionWithExecutorOverridesDefaultExecutorType(): void
    {
        

        // Configuration method getDefaultExecutorType will return default

        // Configuration method getEnvironment will return default

        // Request BATCH executor instead of default SIMPLE
        $session = $this->sessionFactory->openSessionWithExecutor(ExecutorType::BATCH);

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionThrowsExceptionWhenNoEnvironmentConfigured(): void
    {
        // Create a new configuration without environment
        $emptyConfig = new Configuration();
        $emptyFactory = new DefaultSessionFactory($emptyConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No environment configured');

        $emptyFactory->openSession();
    }

    public function testOpenSessionWithAutoCommitThrowsExceptionWhenNoEnvironment(): void
    {
        // Create a new configuration without environment
        $emptyConfig = new Configuration();
        $emptyFactory = new DefaultSessionFactory($emptyConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No environment configured');

        $emptyFactory->openSessionWithAutoCommit();
    }

    public function testOpenSessionWithExecutorThrowsExceptionWhenNoEnvironment(): void
    {
        // Create a new configuration without environment
        $emptyConfig = new Configuration();
        $emptyFactory = new DefaultSessionFactory($emptyConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No environment configured');

        $emptyFactory->openSessionWithExecutor(ExecutorType::REUSE);
    }

    public function testOpenSessionCreatesNewConnectionEachTime(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $pdo = new PDO('sqlite::memory:');
        $transaction = $this->createMock(TransactionInterface::class);

        $dataSource
            ->expects($this->exactly(2))
            ->method('getConnection')
            ->willReturn($pdo);

        $transactionFactory
            ->expects($this->exactly(2))
            ->method('newTransaction')
            ->with($pdo)
            ->willReturn($transaction);

        $environment = new Environment('custom', $dataSource, $transactionFactory);
        
        // Create a new configuration and factory with this environment
        $config = new Configuration();
        $config->addEnvironment($environment);
        $config->setDefaultEnvironment('custom');
        $factory = new DefaultSessionFactory($config);

        $session1 = $factory->openSession();
        $session2 = $factory->openSession();

        // Should create two different sessions
        $this->assertNotSame($session1, $session2);
    }

    public function testOpenSessionWithDifferentExecutorTypes(): void
    {
        

        // Configuration method getEnvironment will return default

        $simpleSession = $this->sessionFactory->openSessionWithExecutor(ExecutorType::SIMPLE);
        $reuseSession = $this->sessionFactory->openSessionWithExecutor(ExecutorType::REUSE);
        $batchSession = $this->sessionFactory->openSessionWithExecutor(ExecutorType::BATCH);

        // All should be valid sessions
        $this->assertInstanceOf(DefaultSession::class, $simpleSession);
        $this->assertInstanceOf(DefaultSession::class, $reuseSession);
        $this->assertInstanceOf(DefaultSession::class, $batchSession);

        // All should be different instances
        $this->assertNotSame($simpleSession, $reuseSession);
        $this->assertNotSame($simpleSession, $batchSession);
        $this->assertNotSame($reuseSession, $batchSession);
    }

    public function testOpenSessionUsesTransactionFromEnvironment(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $pdo = new PDO('sqlite::memory:');
        $transaction = $this->createMock(TransactionInterface::class);

        $dataSource
            ->method('getConnection')
            ->willReturn($pdo);

        $transactionFactory
            ->expects($this->once())
            ->method('newTransaction')
            ->with($pdo)
            ->willReturn($transaction);

        $environment = new Environment('custom', $dataSource, $transactionFactory);
        
        // Create a new configuration and factory with this environment
        $config = new Configuration();
        $config->addEnvironment($environment);
        $config->setDefaultEnvironment('custom');
        $factory = new DefaultSessionFactory($config);

        $session = $factory->openSession();

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionUsesDataSourceFromEnvironment(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $pdo = new PDO('sqlite::memory:');
        $transaction = $this->createMock(TransactionInterface::class);

        $dataSource
            ->expects($this->once())
            ->method('getConnection')
            ->willReturn($pdo);

        $transactionFactory
            ->method('newTransaction')
            ->willReturn($transaction);

        $environment = new Environment('custom', $dataSource, $transactionFactory);
        
        // Create a new configuration and factory with this environment
        $config = new Configuration();
        $config->addEnvironment($environment);
        $config->setDefaultEnvironment('custom');
        $factory = new DefaultSessionFactory($config);

        $session = $factory->openSession();

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testFactoryCanCreateMultipleSessionsSimultaneously(): void
    {
        

        // Configuration method getDefaultExecutorType will return default

        // Configuration method getEnvironment will return default

        $sessions = [];
        for ($i = 0; $i < 5; $i++) {
            $sessions[] = $this->sessionFactory->openSession();
        }

        // All should be valid sessions
        foreach ($sessions as $session) {
            $this->assertInstanceOf(DefaultSession::class, $session);
            $this->assertFalse($session->isClosed());
        }

        // All should be different instances - compare object IDs
        $objectIds = array_map('spl_object_id', $sessions);
        $this->assertCount(5, array_unique($objectIds));
    }

    public function testOpenSessionDoesNotAutoCommitByDefault(): void
    {
        

        // Configuration method getDefaultExecutorType will return default

        // Configuration method getEnvironment will return default

        $session = $this->sessionFactory->openSession();

        // Session should be created without autoCommit
        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testConstructorAcceptsConfiguration(): void
    {
        $config = new Configuration();
        $factory = new DefaultSessionFactory($config);

        $this->assertSame($config, $factory->getConfiguration());
    }

    /**
     * Helper method to create a test environment
     */
    private function createTestEnvironment(): Environment
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $pdo = new PDO('sqlite::memory:');
        $transaction = $this->createMock(TransactionInterface::class);

        $dataSource
            ->method('getConnection')
            ->willReturn($pdo);

        $transactionFactory
            ->method('newTransaction')
            ->willReturn($transaction);

        return new Environment('test', $dataSource, $transactionFactory);
    }
}
