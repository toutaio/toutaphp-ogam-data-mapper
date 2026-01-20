<?php

declare(strict_types=1);

namespace Touta\Ogam\Executor;

use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\ExecutorInterface;
use Touta\Ogam\Hydration\HydratorFactory;
use Touta\Ogam\Logging\QueryLogEntry;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\ParameterMapping;
use Touta\Ogam\Transaction\TransactionInterface;

/**
 * Base executor implementation with common functionality.
 */
abstract class BaseExecutor implements ExecutorInterface
{
    protected bool $closed = false;

    /** @var array<string, list<mixed>> */
    protected array $localCache = [];

    /** @var array{sql: string, params: array<string, mixed>, time: float, rowCount: int|null, statementId: string|null}|null */
    protected ?array $lastQuery = null;

    protected HydratorFactory $hydratorFactory;

    public function __construct(
        protected readonly Configuration $configuration,
        protected readonly TransactionInterface $transaction,
    ) {
        $this->hydratorFactory = new HydratorFactory(
            $this->configuration->getTypeHandlerRegistry(),
            $this->configuration->isMapUnderscoreToCamelCase(),
        );
    }

    public function query(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): array {
        $this->assertNotClosed();

        // Check local cache
        $cacheKey = $this->createCacheKey($statement, $parameter, $boundSql);

        if (isset($this->localCache[$cacheKey])) {
            return $this->localCache[$cacheKey];
        }

        $results = $this->doQuery($statement, $parameter, $boundSql);

        // Store in local cache
        $this->localCache[$cacheKey] = $results;

        return $results;
    }

    public function update(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): int {
        $this->assertNotClosed();

        // Clear local cache on update
        $this->clearLocalCache();

        return $this->doUpdate($statement, $parameter, $boundSql);
    }

    public function flushStatements(): array
    {
        $this->assertNotClosed();

        return $this->doFlushStatements();
    }

    public function commit(bool $required): void
    {
        $this->assertNotClosed();

        $this->clearLocalCache();
        $this->flushStatements();

        if ($required) {
            $this->transaction->commit();
        }
    }

    public function rollback(bool $required): void
    {
        if ($this->closed) {
            return;
        }

        $this->clearLocalCache();

        if ($required) {
            $this->transaction->rollback();
        }
    }

    public function close(bool $forceRollback): void
    {
        if ($this->closed) {
            return;
        }

        try {
            if ($forceRollback) {
                $this->rollback(true);
            } else {
                $this->flushStatements();
            }
        } finally {
            $this->transaction->close();
            $this->clearLocalCache();
            $this->closed = true;
        }
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function clearLocalCache(): void
    {
        $this->localCache = [];
    }

    public function getLastQuery(): ?array
    {
        return $this->lastQuery;
    }

    /**
     * Execute a query and return results.
     *
     * @param MappedStatement $statement The mapped statement
     * @param array<string, mixed>|object|null $parameter The parameters
     * @param BoundSql $boundSql The bound SQL
     *
     * @return list<mixed> The query results
     */
    abstract protected function doQuery(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): array;

    /**
     * Execute an update and return affected rows.
     *
     * @param MappedStatement $statement The mapped statement
     * @param array<string, mixed>|object|null $parameter The parameters
     * @param BoundSql $boundSql The bound SQL
     *
     * @return int The number of rows affected
     */
    abstract protected function doUpdate(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): int;

    /**
     * Flush any queued statements.
     *
     * @return list<int> The row counts for each flushed statement
     */
    abstract protected function doFlushStatements(): array;

    protected function getConnection(): PDO
    {
        return $this->transaction->getConnection();
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    protected function prepareStatement(BoundSql $boundSql, array|object|null $parameter): PDOStatement
    {
        $connection = $this->getConnection();
        $stmt = $connection->prepare($boundSql->getSql());

        $this->bindParameters($stmt, $boundSql, $parameter);

        return $stmt;
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    protected function bindParameters(
        PDOStatement $stmt,
        BoundSql $boundSql,
        array|object|null $parameter,
    ): void {
        $parameterValues = $this->extractParameterValues($parameter);

        // Merge additional parameters from BoundSql (e.g., from foreach bindings)
        $additionalParams = $boundSql->getAdditionalParameters();

        if ($additionalParams !== []) {
            $parameterValues = array_merge($parameterValues, $additionalParams);
        }

        $registry = $this->configuration->getTypeHandlerRegistry();

        foreach ($boundSql->getParameterMappings() as $index => $mapping) {
            $value = $this->getParameterValue($parameterValues, $mapping);
            $handler = $registry->getHandlerForValue($value);

            $handler->setParameter(
                $stmt,
                $index + 1,
                $value,
                $mapping->getSqlType(),
            );
        }
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     *
     * @return array<string, mixed>
     */
    protected function extractParameterValues(array|object|null $parameter): array
    {
        if ($parameter === null) {
            return [];
        }

        if (\is_array($parameter)) {
            return $parameter;
        }

        // Convert object to array
        $values = [];
        $reflection = new ReflectionClass($parameter);

        foreach ($reflection->getProperties() as $prop) {
            $prop->setAccessible(true);
            $values[$prop->getName()] = $prop->getValue($parameter);
        }

        // Also include getter methods
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if (str_starts_with($name, 'get') && $method->getNumberOfRequiredParameters() === 0) {
                $property = lcfirst(substr($name, 3));

                if (!isset($values[$property])) {
                    $values[$property] = $method->invoke($parameter);
                }
            }

            if (str_starts_with($name, 'is') && $method->getNumberOfRequiredParameters() === 0) {
                $property = lcfirst(substr($name, 2));

                if (!isset($values[$property])) {
                    $values[$property] = $method->invoke($parameter);
                }
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $parameterValues
     */
    protected function getParameterValue(array $parameterValues, ParameterMapping $mapping): mixed
    {
        $property = $mapping->getProperty();

        // Handle nested properties (e.g., 'user.name')
        if (str_contains($property, '.')) {
            return $this->getNestedValue($parameterValues, $property);
        }

        return $parameterValues[$property] ?? null;
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function getNestedValue(array $values, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $values;

        foreach ($parts as $part) {
            if (\is_array($current) && \array_key_exists($part, $current)) {
                $current = $current[$part];
            } elseif (\is_object($current)) {
                $current = $this->getObjectProperty($current, $part);
            } else {
                return null;
            }
        }

        return $current;
    }

    protected function getObjectProperty(object $object, string $property): mixed
    {
        // Try getter first
        $getter = 'get' . ucfirst($property);

        if (method_exists($object, $getter)) {
            return $object->{$getter}();
        }

        // Try boolean getter
        $isGetter = 'is' . ucfirst($property);

        if (method_exists($object, $isGetter)) {
            return $object->{$isGetter}();
        }

        // Try direct property access
        if (property_exists($object, $property)) {
            $reflection = new ReflectionProperty($object, $property);
            $reflection->setAccessible(true);

            return $reflection->getValue($object);
        }

        return null;
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    protected function createCacheKey(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): string {
        $data = [
            'id' => $statement->getFullId(),
            'sql' => $boundSql->getSql(),
            'params' => $this->extractParameterValues($parameter),
        ];

        return hash('xxh3', serialize($data));
    }

    /**
     * Hydrate query results.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<mixed>
     */
    protected function hydrateResults(MappedStatement $statement, array $rows): array
    {
        $hydration = $statement->getHydration() ?? Hydration::OBJECT;
        $hydrator = $this->hydratorFactory->create($hydration);

        $resultMap = null;

        if ($statement->getResultMapId() !== null) {
            $resultMap = $this->configuration->getResultMap($statement->getResultMapId());
        }

        return $hydrator->hydrateAll($rows, $resultMap, $statement->getResultType());
    }

    protected function assertNotClosed(): void
    {
        if ($this->closed) {
            throw new RuntimeException('Executor is closed');
        }
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    protected function recordQuery(
        BoundSql $boundSql,
        array|object|null $parameter,
        float $startTime,
        ?string $statementId = null,
        ?int $rowCount = null,
    ): void {
        $executionTimeMs = (microtime(true) - $startTime) * 1000;
        $params = $this->extractParameterValues($parameter);

        $this->lastQuery = [
            'sql' => $boundSql->getSql(),
            'params' => $params,
            'time' => $executionTimeMs / 1000, // Keep in seconds for backward compatibility
            'rowCount' => $rowCount,
            'statementId' => $statementId,
        ];

        // Log to QueryLogger if debug mode is enabled
        if ($this->configuration->isDebugMode()) {
            $logger = $this->configuration->getQueryLogger();

            if ($logger !== null) {
                $entry = new QueryLogEntry(
                    $boundSql->getSql(),
                    $params,
                    $executionTimeMs,
                    $rowCount,
                    $statementId,
                );

                $logger->log($entry);
            }
        }
    }
}
