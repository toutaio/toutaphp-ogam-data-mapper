<?php

declare(strict_types=1);

namespace Touta\Ogam\Logging;

/**
 * In-memory query logger for debugging and testing.
 *
 * Stores all logged queries in memory for inspection.
 * Useful for debugging, testing, and development profiling.
 */
final class InMemoryQueryLogger implements QueryLoggerInterface
{
    /** @var list<QueryLogEntry> */
    private array $entries = [];

    public function log(QueryLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return list<QueryLogEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * Get the total execution time of all logged queries.
     */
    public function getTotalExecutionTime(): float
    {
        return array_sum(array_map(
            fn(QueryLogEntry $entry) => $entry->executionTimeMs,
            $this->entries,
        ));
    }

    /**
     * Get the number of logged queries.
     */
    public function getQueryCount(): int
    {
        return \count($this->entries);
    }

    /**
     * Get the last logged entry.
     */
    public function getLastEntry(): ?QueryLogEntry
    {
        if (\count($this->entries) === 0) {
            return null;
        }

        return $this->entries[\count($this->entries) - 1];
    }
}
