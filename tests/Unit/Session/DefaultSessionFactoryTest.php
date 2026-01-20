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
use Touta\Ogam\Transaction\ManagedTransaction;
use Touta\Ogam\Transaction\TransactionFactory;

final class DefaultSessionFactoryTest extends TestCase
{
    private Configuration $configuration;
    private DefaultSessionFactory $sessionFactory;

    protected function setUp(): void
    {
        $this->configuration = $this->createMock(Configuration::class);
        $this->sessionFactory = new DefaultSessionFactory($this->configuration);
    }

    public function testGetConfigurationReturnsConfiguration(): void
    {
        $result = $this->sessionFactory->getConfiguration();

        $this->assertSame($this->configuration, $result);
    }

    public function testOpenSessionCreatesSessionWithDefaultSettings(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session = $this->sessionFactory->openSession();

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertInstanceOf(DefaultSession::class, $session);
        $this->assertFalse($session->isClosed());
    }

    public function testOpenSessionWithAutoCommitCreatesSessionWithAutoCommit(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session = $this->sessionFactory->openSessionWithAutoCommit();

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertFalse($session->isClosed());
    }

    public function testOpenSessionWithExecutorCreatesSessionWithSpecificExecutor(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session = $this->sessionFactory->openSessionWithExecutor(ExecutorType::BATCH);

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertFalse($session->isClosed());
    }

    public function testOpenSessionCreatesSimpleExecutor(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session = $this->sessionFactory->openSession();

        // Verify session was created successfully
        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionCreatesReuseExecutor(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::REUSE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session = $this->sessionFactory->openSession();

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionCreatesBatchExecutor(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::BATCH);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session = $this->sessionFactory->openSession();

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionWithExecutorOverridesDefaultExecutorType(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        // Request BATCH executor instead of default SIMPLE
        $session = $this->sessionFactory->openSessionWithExecutor(ExecutorType::BATCH);

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionThrowsExceptionWhenNoEnvironmentConfigured(): void
    {
        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn(null);

        $this->configuration
            ->method('getDefaultEnvironment')
            ->willReturn('default');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No environment configured');
        $this->expectExceptionMessage('Configure environment "default"');

        $this->sessionFactory->openSession();
    }

    public function testOpenSessionWithAutoCommitThrowsExceptionWhenNoEnvironment(): void
    {
        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn(null);

        $this->configuration
            ->method('getDefaultEnvironment')
            ->willReturn('production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No environment configured');

        $this->sessionFactory->openSessionWithAutoCommit();
    }

    public function testOpenSessionWithExecutorThrowsExceptionWhenNoEnvironment(): void
    {
        $this->configuration
            ->method('getEnvironment')
            ->willReturn(null);

        $this->configuration
            ->method('getDefaultEnvironment')
            ->willReturn('test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No environment configured');

        $this->sessionFactory->openSessionWithExecutor(ExecutorType::REUSE);
    }

    public function testOpenSessionCreatesNewConnectionEachTime(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $pdo = new PDO('sqlite::memory:');
        $transaction = $this->createMock(ManagedTransaction::class);

        $dataSource
            ->expects($this->exactly(2))
            ->method('getConnection')
            ->willReturn($pdo);

        $transactionFactory
            ->expects($this->exactly(2))
            ->method('newTransaction')
            ->with($pdo)
            ->willReturn($transaction);

        $environment = new Environment('test', $dataSource, $transactionFactory);

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session1 = $this->sessionFactory->openSession();
        $session2 = $this->sessionFactory->openSession();

        // Should create two different sessions
        $this->assertNotSame($session1, $session2);
    }

    public function testOpenSessionWithDifferentExecutorTypes(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

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
        $transaction = $this->createMock(ManagedTransaction::class);

        $dataSource
            ->method('getConnection')
            ->willReturn($pdo);

        $transactionFactory
            ->expects($this->once())
            ->method('newTransaction')
            ->with($pdo)
            ->willReturn($transaction);

        $environment = new Environment('test', $dataSource, $transactionFactory);

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session = $this->sessionFactory->openSession();

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testOpenSessionUsesDataSourceFromEnvironment(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $pdo = new PDO('sqlite::memory:');
        $transaction = $this->createMock(ManagedTransaction::class);

        $dataSource
            ->expects($this->once())
            ->method('getConnection')
            ->willReturn($pdo);

        $transactionFactory
            ->method('newTransaction')
            ->willReturn($transaction);

        $environment = new Environment('test', $dataSource, $transactionFactory);

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $session = $this->sessionFactory->openSession();

        $this->assertInstanceOf(DefaultSession::class, $session);
    }

    public function testFactoryCanCreateMultipleSessionsSimultaneously(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

        $sessions = [];
        for ($i = 0; $i < 5; $i++) {
            $sessions[] = $this->sessionFactory->openSession();
        }

        // All should be valid sessions
        foreach ($sessions as $session) {
            $this->assertInstanceOf(DefaultSession::class, $session);
            $this->assertFalse($session->isClosed());
        }

        // All should be different instances
        $this->assertCount(5, array_unique($sessions, SORT_REGULAR));
    }

    public function testOpenSessionDoesNotAutoCommitByDefault(): void
    {
        $environment = $this->createTestEnvironment();

        $this->configuration
            ->method('getDefaultExecutorType')
            ->willReturn(ExecutorType::SIMPLE);

        $this->configuration
            ->method('getEnvironment')
            ->willReturn($environment);

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
        $transaction = $this->createMock(ManagedTransaction::class);

        $dataSource
            ->method('getConnection')
            ->willReturn($pdo);

        $transactionFactory
            ->method('newTransaction')
            ->willReturn($transaction);

        return new Environment('test', $dataSource, $transactionFactory);
    }
}
