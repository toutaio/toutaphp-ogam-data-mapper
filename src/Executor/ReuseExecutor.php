<?php

declare(strict_types=1);

namespace Touta\Ogam\Executor;

use PDO;
use PDOException;
use PDOStatement;
use ReflectionProperty;
use Touta\Ogam\Configuration;
use Touta\Ogam\Exception\SqlException;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Transaction\TransactionInterface;

/**
 * Executor that reuses prepared statements across executions.
 *
 * Improves performance when the same SQL is executed multiple times.
 */
final class ReuseExecutor extends BaseExecutor
{
    /** @var array<string, PDOStatement> */
    private array $statementCache = [];

    public function __construct(
        Configuration $configuration,
        TransactionInterface $transaction,
    ) {
        parent::__construct($configuration, $transaction);
    }

    public function close(bool $forceRollback): void
    {
        $this->clearStatementCache();
        parent::close($forceRollback);
    }

    protected function doQuery(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): array {
        $startTime = microtime(true);

        $stmt = $this->getOrPrepareStatement($boundSql);
        $this->bindParameters($stmt, $boundSql, $parameter);

        try {
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw SqlException::fromPdoException(
                $e,
                $boundSql->getSql(),
                $this->extractParameterValues($parameter),
            );
        }

        $this->recordQuery(
            $boundSql,
            $parameter,
            $startTime,
            $statement->getFullId(),
            \count($rows),
        );

        return $this->hydrateResults($statement, $rows);
    }

    protected function doUpdate(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): int {
        $startTime = microtime(true);

        $stmt = $this->getOrPrepareStatement($boundSql);
        $this->bindParameters($stmt, $boundSql, $parameter);

        try {
            $stmt->execute();
            $rowCount = $stmt->rowCount();
        } catch (PDOException $e) {
            throw SqlException::fromPdoException(
                $e,
                $boundSql->getSql(),
                $this->extractParameterValues($parameter),
            );
        }

        $this->recordQuery(
            $boundSql,
            $parameter,
            $startTime,
            $statement->getFullId(),
            $rowCount,
        );

        // Handle generated keys
        if ($statement->isUseGeneratedKeys() && $parameter !== null) {
            $this->setGeneratedKey($statement, $parameter);
        }

        return $rowCount;
    }

    protected function doFlushStatements(): array
    {
        // Reuse executor doesn't batch statements
        return [];
    }

    private function getOrPrepareStatement(BoundSql $boundSql): PDOStatement
    {
        $sql = $boundSql->getSql();
        $cacheKey = hash('xxh3', $sql);

        if (!isset($this->statementCache[$cacheKey])) {
            try {
                $this->statementCache[$cacheKey] = $this->getConnection()->prepare($sql);
            } catch (PDOException $e) {
                throw SqlException::fromPdoException($e, $sql, []);
            }
        }

        return $this->statementCache[$cacheKey];
    }

    private function clearStatementCache(): void
    {
        $this->statementCache = [];
    }

    /**
     * @param array<string, mixed>|object $parameter
     */
    private function setGeneratedKey(MappedStatement $statement, array|object $parameter): void
    {
        $keyProperty = $statement->getKeyProperty();

        if ($keyProperty === null) {
            return;
        }

        $generatedId = $this->getConnection()->lastInsertId();

        if ($generatedId === false || $generatedId === '0') {
            return;
        }

        if (\is_array($parameter)) {
            $parameter[$keyProperty] = $generatedId;

            return;
        }

        // Set on object
        $setter = 'set' . ucfirst($keyProperty);

        if (method_exists($parameter, $setter)) {
            $parameter->{$setter}($generatedId);

            return;
        }

        // Try direct property
        if (property_exists($parameter, $keyProperty)) {
            $reflection = new ReflectionProperty($parameter, $keyProperty);

            if (!$reflection->isReadOnly()) {
                $reflection->setAccessible(true);
                $reflection->setValue($parameter, $generatedId);
            }
        }
    }
}
