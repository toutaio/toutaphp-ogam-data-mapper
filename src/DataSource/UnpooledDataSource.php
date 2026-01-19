<?php

declare(strict_types=1);

namespace Touta\Ogam\DataSource;

use PDO;
use Touta\Ogam\Contract\DataSourceInterface;

/**
 * Data source that creates a new connection each time but tracks them.
 *
 * Useful for debugging and development environments.
 */
final class UnpooledDataSource implements DataSourceInterface
{
    /** @var array<int, mixed> */
    private array $options;

    private int $connectionCount = 0;

    /**
     * @param string $dsn The Data Source Name
     * @param string|null $username Database username
     * @param string|null $password Database password
     * @param array<int, mixed> $options PDO options
     */
    public function __construct(
        private readonly string $dsn,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        array $options = [],
    ) {
        $this->options = $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    public function getConnection(): PDO
    {
        $this->connectionCount++;

        return new PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options,
        );
    }

    /**
     * Get the number of connections created.
     */
    public function getConnectionCount(): int
    {
        return $this->connectionCount;
    }

    /**
     * Reset the connection counter.
     */
    public function resetConnectionCount(): void
    {
        $this->connectionCount = 0;
    }
}
