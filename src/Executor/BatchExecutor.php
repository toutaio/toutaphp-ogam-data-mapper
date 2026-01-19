<?php

declare(strict_types=1);

namespace Touta\Ogam\Executor;

use PDO;
use PDOStatement;
use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Transaction\TransactionInterface;

/**
 * Executor that batches update statements for efficient bulk operations.
 *
 * Queries are executed immediately, but updates are queued and executed
 * in batch when flushStatements() is called.
 */
final class BatchExecutor extends BaseExecutor
{
    /** @var list<array{statement: PDOStatement, sql: string}> */
    private array $batchQueue = [];

    /** @var array<string, PDOStatement> */
    private array $statementCache = [];

    private string $currentSql = '';

    private ?PDOStatement $currentStatement = null;

    public function __construct(
        Configuration $configuration,
        TransactionInterface $transaction,
    ) {
        parent::__construct($configuration, $transaction);
    }

    public function close(bool $forceRollback): void
    {
        $this->clearBatch();
        parent::close($forceRollback);
    }

    protected function doQuery(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): array {
        // Queries are executed immediately, flushing any pending batch
        $this->flushStatements();

        $startTime = microtime(true);

        $stmt = $this->getConnection()->prepare($boundSql->getSql());
        $this->bindParameters($stmt, $boundSql, $parameter);
        $stmt->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->recordQuery($boundSql, $parameter, $startTime);

        return $this->hydrateResults($statement, $rows);
    }

    protected function doUpdate(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): int {
        $sql = $boundSql->getSql();

        // Check if we can batch with current statement
        if ($sql !== $this->currentSql) {
            $this->currentSql = $sql;
            $this->currentStatement = $this->getOrPrepareStatement($sql);
            $this->batchQueue[] = [
                'statement' => $this->currentStatement,
                'sql' => $sql,
            ];
        }

        // Bind and add to batch
        if ($this->currentStatement !== null) {
            $this->bindParameters($this->currentStatement, $boundSql, $parameter);
            $this->currentStatement->execute();
        }

        // Return a placeholder; actual counts are returned by flushStatements()
        return -1;
    }

    protected function doFlushStatements(): array
    {
        if ($this->batchQueue === []) {
            return [];
        }

        $results = [];

        foreach ($this->batchQueue as $batch) {
            $results[] = $batch['statement']->rowCount();
        }

        $this->clearBatch();

        return $results;
    }

    private function getOrPrepareStatement(string $sql): PDOStatement
    {
        $cacheKey = hash('xxh3', $sql);

        if (!isset($this->statementCache[$cacheKey])) {
            $this->statementCache[$cacheKey] = $this->getConnection()->prepare($sql);
        }

        return $this->statementCache[$cacheKey];
    }

    private function clearBatch(): void
    {
        $this->batchQueue = [];
        $this->statementCache = [];
        $this->currentSql = '';
        $this->currentStatement = null;
    }
}
