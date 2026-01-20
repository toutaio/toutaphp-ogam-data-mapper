<?php

declare(strict_types=1);

namespace Touta\Ogam\Executor;

use PDO;
use PDOException;
use ReflectionProperty;
use Touta\Ogam\Exception\SqlException;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\MappedStatement;

/**
 * Simple executor that creates a new prepared statement for each execution.
 *
 * This is the default executor strategy.
 */
final class SimpleExecutor extends BaseExecutor
{
    protected function doQuery(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): array {
        $startTime = microtime(true);

        $stmt = $this->prepareStatement($boundSql, $parameter);

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

        $stmt = $this->prepareStatement($boundSql, $parameter);

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
        // Simple executor doesn't batch statements
        return [];
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
