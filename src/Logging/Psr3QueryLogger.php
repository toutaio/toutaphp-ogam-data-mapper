<?php

declare(strict_types=1);

namespace Touta\Ogam\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-3 adapter for query logging.
 *
 * Logs query executions to a PSR-3 compatible logger.
 * Does not store entries in memory - use InMemoryQueryLogger for that.
 */
final class Psr3QueryLogger implements QueryLoggerInterface
{
    /**
     * @param LoggerInterface $logger The PSR-3 logger to write to
     * @param string $level The log level (default: debug)
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $level = LogLevel::DEBUG,
    ) {}

    public function log(QueryLogEntry $entry): void
    {
        $message = \sprintf(
            '[%.2fms] %s',
            $entry->executionTimeMs,
            $entry->sql,
        );

        $context = [
            'sql' => $entry->sql,
            'parameters' => $entry->parameters,
            'execution_time_ms' => $entry->executionTimeMs,
        ];

        if ($entry->rowCount !== null) {
            $context['row_count'] = $entry->rowCount;
        }

        if ($entry->statementId !== null) {
            $context['statement_id'] = $entry->statementId;
        }

        $this->logger->log($this->level, $message, $context);
    }

    /**
     * PSR-3 logger does not store entries.
     *
     * @return list<QueryLogEntry>
     */
    public function getEntries(): array
    {
        return [];
    }

    /**
     * No-op for PSR-3 logger.
     */
    public function clear(): void
    {
        // PSR-3 logger doesn't store entries
    }
}
