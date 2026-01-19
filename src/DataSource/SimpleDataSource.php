<?php

declare(strict_types=1);

namespace Touta\Ogam\DataSource;

use PDO;
use Touta\Ogam\Contract\DataSourceInterface;

/**
 * Simple data source that creates a new PDO connection.
 *
 * Each call to getConnection() returns a new PDO instance.
 */
final class SimpleDataSource implements DataSourceInterface
{
    /** @var array<int, mixed> */
    private array $options;

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
        return new PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options,
        );
    }

    public function getDsn(): string
    {
        return $this->dsn;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @return array<int, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
