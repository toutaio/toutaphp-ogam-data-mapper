<?php

declare(strict_types=1);

namespace Touta\Ogam\DataSource;

use PDO;
use Touta\Ogam\Contract\DataSourceInterface;

/**
 * Data source with connection pooling.
 *
 * Maintains a pool of reusable PDO connections.
 */
final class PooledDataSource implements DataSourceInterface
{
    /** @var list<PDO> */
    private array $pool = [];

    /** @var array<int, mixed> */
    private array $options;

    private int $currentSize = 0;

    /**
     * @param string $dsn The Data Source Name
     * @param string|null $username Database username
     * @param string|null $password Database password
     * @param array<int, mixed> $options PDO options
     * @param int $maxSize Maximum pool size
     */
    public function __construct(
        private readonly string $dsn,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        array $options = [],
        private readonly int $maxSize = 10,
    ) {
        $this->options = $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    public function getConnection(): PDO
    {
        // Return a pooled connection if available
        if ($this->pool !== []) {
            return array_pop($this->pool);
        }

        // Create a new connection
        $this->currentSize++;

        return $this->createConnection();
    }

    /**
     * Return a connection to the pool.
     */
    public function releaseConnection(PDO $connection): void
    {
        // Only pool if we haven't exceeded max size
        if (\count($this->pool) < $this->maxSize) {
            // Rollback any open transaction
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->pool[] = $connection;
        }
    }

    /**
     * Get the current number of connections in the pool.
     */
    public function getPoolSize(): int
    {
        return \count($this->pool);
    }

    /**
     * Get the total number of connections created.
     */
    public function getTotalConnections(): int
    {
        return $this->currentSize;
    }

    /**
     * Clear all pooled connections.
     */
    public function clear(): void
    {
        $this->pool = [];
    }

    private function createConnection(): PDO
    {
        return new PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options,
        );
    }
}
