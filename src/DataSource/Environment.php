<?php

declare(strict_types=1);

namespace Touta\Ogam\DataSource;

use Touta\Ogam\Contract\DataSourceInterface;
use Touta\Ogam\Transaction\TransactionFactory;

/**
 * Represents a database environment configuration.
 *
 * An environment combines a data source and transaction factory.
 */
final class Environment
{
    /**
     * @param string $id The environment ID (e.g., 'development', 'production')
     * @param DataSourceInterface $dataSource The data source
     * @param TransactionFactory $transactionFactory The transaction factory
     */
    public function __construct(
        private readonly string $id,
        private readonly DataSourceInterface $dataSource,
        private readonly TransactionFactory $transactionFactory,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getDataSource(): DataSourceInterface
    {
        return $this->dataSource;
    }

    public function getTransactionFactory(): TransactionFactory
    {
        return $this->transactionFactory;
    }
}
