<?php

declare(strict_types=1);

namespace Touta\Ogam\Session;

use Generator;
use InvalidArgumentException;
use ReflectionProperty;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\ExecutorInterface;
use Touta\Ogam\Contract\SessionInterface;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Sql\SqlSource;

/**
 * Default Session implementation.
 *
 * Manages statement execution, transactions, and mapper proxies.
 */
final class DefaultSession implements SessionInterface
{
    private bool $closed = false;

    private bool $dirty = false;

    /** @var array<class-string, object> */
    private array $mapperCache = [];

    public function __construct(
        private readonly Configuration $configuration,
        private readonly ExecutorInterface $executor,
        private readonly bool $autoCommit = false,
    ) {}

    public function selectOne(
        string $statement,
        array|object|null $parameter = null,
        Hydration $hydration = Hydration::OBJECT,
    ): mixed {
        $results = $this->selectList($statement, $parameter, $hydration);

        if (\count($results) === 0) {
            return null;
        }

        if (\count($results) > 1) {
            throw new RuntimeException(
                \sprintf(
                    'Expected one result (or null) to be returned by selectOne(), but found: %d',
                    \count($results),
                ),
            );
        }

        return $results[0];
    }

    public function selectList(
        string $statement,
        array|object|null $parameter = null,
        Hydration $hydration = Hydration::OBJECT,
    ): array {
        $this->assertNotClosed();

        $mappedStatement = $this->getMappedStatement($statement);
        $this->assertStatementType($mappedStatement, StatementType::SELECT);

        // Override hydration if specified
        if ($hydration !== $mappedStatement->getHydration()) {
            $mappedStatement = $this->withHydration($mappedStatement, $hydration);
        }

        $boundSql = $this->getBoundSql($mappedStatement, $parameter);

        return $this->executor->query($mappedStatement, $parameter, $boundSql);
    }

    public function selectMap(
        string $statement,
        string $mapKey,
        array|object|null $parameter = null,
    ): array {
        $results = $this->selectList($statement, $parameter);
        $map = [];

        foreach ($results as $result) {
            $key = $this->extractMapKey($result, $mapKey);

            if (\is_int($key) || \is_string($key)) {
                $map[$key] = $result;
            }
        }

        return $map;
    }

    public function selectCursor(
        string $statement,
        array|object|null $parameter = null,
    ): iterable {
        $this->assertNotClosed();

        $mappedStatement = $this->getMappedStatement($statement);
        $this->assertStatementType($mappedStatement, StatementType::SELECT);

        // Return a generator for lazy iteration
        return $this->createCursor($mappedStatement, $parameter);
    }

    public function insert(string $statement, array|object|null $parameter = null): int
    {
        return $this->executeUpdate($statement, $parameter, StatementType::INSERT);
    }

    public function update(string $statement, array|object|null $parameter = null): int
    {
        return $this->executeUpdate($statement, $parameter, StatementType::UPDATE);
    }

    public function delete(string $statement, array|object|null $parameter = null): int
    {
        return $this->executeUpdate($statement, $parameter, StatementType::DELETE);
    }

    public function commit(): void
    {
        $this->assertNotClosed();
        $this->executor->commit(!$this->autoCommit);
        $this->dirty = false;
    }

    public function rollback(): void
    {
        $this->assertNotClosed();
        $this->executor->rollback(!$this->autoCommit);
        $this->dirty = false;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $mapperInterface
     *
     * @return T
     */
    public function getMapper(string $mapperInterface): object
    {
        $this->assertNotClosed();

        if (!isset($this->mapperCache[$mapperInterface])) {
            $this->mapperCache[$mapperInterface] = new MapperProxy(
                $this,
                $mapperInterface,
                $this->configuration,
            );
        }

        /** @var T */
        return $this->mapperCache[$mapperInterface];
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->executor->close($this->dirty && !$this->autoCommit);
        $this->mapperCache = [];
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function clearCache(): void
    {
        $this->assertNotClosed();
        $this->executor->clearLocalCache();
    }

    public function getLastQuery(): ?array
    {
        return $this->executor->getLastQuery();
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    private function executeUpdate(
        string $statement,
        array|object|null $parameter,
        StatementType $expectedType,
    ): int {
        $this->assertNotClosed();

        $mappedStatement = $this->getMappedStatement($statement);
        $this->assertStatementType($mappedStatement, $expectedType);

        $boundSql = $this->getBoundSql($mappedStatement, $parameter);

        $this->dirty = true;

        return $this->executor->update($mappedStatement, $parameter, $boundSql);
    }

    private function getMappedStatement(string $id): MappedStatement
    {
        $statement = $this->configuration->getMappedStatement($id);

        if ($statement === null) {
            throw new InvalidArgumentException(
                \sprintf('Mapped statement "%s" not found', $id),
            );
        }

        return $statement;
    }

    private function assertStatementType(MappedStatement $statement, StatementType $expected): void
    {
        $actual = $statement->getType();

        // Allow some flexibility: INSERT/UPDATE/DELETE can be used interchangeably for updates
        $updateTypes = [StatementType::INSERT, StatementType::UPDATE, StatementType::DELETE];

        if ($actual === $expected) {
            return;
        }

        if (\in_array($actual, $updateTypes, true) && \in_array($expected, $updateTypes, true)) {
            return;
        }

        throw new RuntimeException(
            \sprintf(
                'Statement "%s" is a %s statement, but was used as %s',
                $statement->getFullId(),
                $actual->value,
                $expected->value,
            ),
        );
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    private function getBoundSql(MappedStatement $statement, array|object|null $parameter): BoundSql
    {
        $sqlSource = $statement->getSqlSource();

        if ($sqlSource instanceof SqlSource) {
            return $sqlSource->getBoundSql($parameter);
        }

        // Fallback for static SQL
        return new BoundSql($statement->getSql() ?? '', []);
    }

    private function extractMapKey(mixed $result, string $mapKey): mixed
    {
        if (\is_array($result)) {
            return $result[$mapKey] ?? throw new RuntimeException(
                \sprintf('Map key "%s" not found in result array', $mapKey),
            );
        }

        if (\is_object($result)) {
            $getter = 'get' . ucfirst($mapKey);

            if (method_exists($result, $getter)) {
                return $result->{$getter}();
            }

            if (property_exists($result, $mapKey)) {
                $reflection = new ReflectionProperty($result, $mapKey);
                $reflection->setAccessible(true);

                return $reflection->getValue($result);
            }
        }

        throw new RuntimeException(
            \sprintf('Cannot extract map key "%s" from result', $mapKey),
        );
    }

    /**
     * @param array<string, mixed>|object|null $parameter
     *
     * @return Generator<mixed>
     */
    private function createCursor(MappedStatement $statement, array|object|null $parameter): Generator
    {
        $boundSql = $this->getBoundSql($statement, $parameter);

        // For cursor, we need to manually execute and iterate
        $connection = $this->executor->getLastQuery();

        // Fall back to normal query and yield results
        $results = $this->executor->query($statement, $parameter, $boundSql);

        foreach ($results as $result) {
            yield $result;
        }
    }

    private function withHydration(MappedStatement $statement, Hydration $hydration): MappedStatement
    {
        return new MappedStatement(
            $statement->getId(),
            $statement->getNamespace(),
            $statement->getType(),
            $statement->getSql(),
            $statement->getResultMapId(),
            $statement->getResultType(),
            $statement->getParameterType(),
            $statement->isUseGeneratedKeys(),
            $statement->getKeyProperty(),
            $statement->getKeyColumn(),
            $statement->getTimeout(),
            $statement->getFetchSize(),
            $hydration,
            $statement->getSqlSource(),
        );
    }

    private function assertNotClosed(): void
    {
        if ($this->closed) {
            throw new RuntimeException('Session is closed');
        }
    }
}
