<?php

declare(strict_types=1);

namespace Touta\Ogam\Session;

use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\SessionFactoryInterface;
use Touta\Ogam\Contract\SessionInterface;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\Executor\BatchExecutor;
use Touta\Ogam\Executor\ExecutorType;
use Touta\Ogam\Executor\ReuseExecutor;
use Touta\Ogam\Executor\SimpleExecutor;

/**
 * Default SessionFactory implementation.
 *
 * Creates sessions with configured executor types and environments.
 */
final class DefaultSessionFactory implements SessionFactoryInterface
{
    public function __construct(
        private readonly Configuration $configuration,
    ) {}

    public function openSession(): SessionInterface
    {
        return $this->openSessionWithOptions(
            $this->configuration->getDefaultExecutorType(),
            false,
        );
    }

    public function openSessionWithAutoCommit(): SessionInterface
    {
        return $this->openSessionWithOptions(
            $this->configuration->getDefaultExecutorType(),
            true,
        );
    }

    public function openSessionWithExecutor(ExecutorType $executorType): SessionInterface
    {
        return $this->openSessionWithOptions($executorType, false);
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    private function openSessionWithOptions(ExecutorType $executorType, bool $autoCommit): SessionInterface
    {
        $environment = $this->getEnvironment();
        $dataSource = $environment->getDataSource();
        $transactionFactory = $environment->getTransactionFactory();

        $connection = $dataSource->getConnection();
        $transaction = $transactionFactory->newTransaction($connection);

        $executor = match ($executorType) {
            ExecutorType::SIMPLE => new SimpleExecutor($this->configuration, $transaction),
            ExecutorType::REUSE => new ReuseExecutor($this->configuration, $transaction),
            ExecutorType::BATCH => new BatchExecutor($this->configuration, $transaction),
        };

        return new DefaultSession($this->configuration, $executor, $autoCommit);
    }

    private function getEnvironment(): Environment
    {
        $environment = $this->configuration->getEnvironment();

        if ($environment === null) {
            throw new \RuntimeException(
                \sprintf(
                    'No environment configured. Configure environment "%s" or set a different default.',
                    $this->configuration->getDefaultEnvironment(),
                ),
            );
        }

        return $environment;
    }
}
