<?php

declare(strict_types=1);

namespace Touta\Ogam\Contract;

use PDO;

/**
 * Provides database connections.
 *
 * Implementations may provide simple connections, connection pooling,
 * or container-managed connections.
 */
interface DataSourceInterface
{
    /**
     * Get a database connection.
     *
     * @return PDO A PDO connection
     */
    public function getConnection(): PDO;
}
