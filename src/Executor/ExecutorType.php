<?php

declare(strict_types=1);

namespace Touta\Ogam\Executor;

/**
 * Defines the execution strategy for SQL statements.
 */
enum ExecutorType: string
{
    /**
     * Creates a new prepared statement for each execution.
     *
     * Best for: General usage, infrequent queries.
     */
    case SIMPLE = 'simple';

    /**
     * Reuses prepared statements across executions.
     *
     * Best for: Repeated queries with the same SQL.
     */
    case REUSE = 'reuse';

    /**
     * Queues updates for batch execution.
     *
     * Best for: Bulk inserts/updates.
     */
    case BATCH = 'batch';
}
