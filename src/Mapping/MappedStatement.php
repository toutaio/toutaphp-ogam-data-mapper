<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

use Touta\Ogam\Sql\SqlSource;

/**
 * Represents a mapped SQL statement.
 *
 * Contains all the information needed to execute a statement
 * and map its results.
 */
final class MappedStatement
{
    /**
     * @param string $id The unique statement ID (e.g., 'findById')
     * @param string $namespace The mapper namespace
     * @param StatementType $type The statement type (SELECT, INSERT, etc.)
     * @param string|null $sql The raw SQL template (null if using SqlSource)
     * @param string|null $resultMapId The result map ID for complex mapping
     * @param string|null $resultType The result type class for simple mapping
     * @param string|null $parameterType The parameter type class
     * @param bool $useGeneratedKeys Whether to retrieve generated keys
     * @param string|null $keyProperty The property to set with the generated key
     * @param string|null $keyColumn The column containing the generated key
     * @param int $timeout Statement timeout in milliseconds (0 = no timeout)
     * @param int $fetchSize Hint for the number of rows to fetch
     * @param Hydration|null $hydration The hydration mode
     * @param SqlSource|null $sqlSource The dynamic SQL source
     * @param bool $flushCache Whether to flush the cache before execution
     * @param bool $useCache Whether to use the second-level cache
     */
    public function __construct(
        private readonly string $id,
        private readonly string $namespace,
        private readonly StatementType $type,
        private readonly ?string $sql = null,
        private readonly ?string $resultMapId = null,
        private readonly ?string $resultType = null,
        private readonly ?string $parameterType = null,
        private readonly bool $useGeneratedKeys = false,
        private readonly ?string $keyProperty = null,
        private readonly ?string $keyColumn = null,
        private readonly int $timeout = 0,
        private readonly int $fetchSize = 0,
        private readonly ?Hydration $hydration = null,
        private readonly ?SqlSource $sqlSource = null,
        private readonly bool $flushCache = false,
        private readonly bool $useCache = true,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getType(): StatementType
    {
        return $this->type;
    }

    public function getSql(): ?string
    {
        return $this->sql;
    }

    public function getResultMapId(): ?string
    {
        return $this->resultMapId;
    }

    public function getResultType(): ?string
    {
        return $this->resultType;
    }

    public function getParameterType(): ?string
    {
        return $this->parameterType;
    }

    public function isUseGeneratedKeys(): bool
    {
        return $this->useGeneratedKeys;
    }

    public function getKeyProperty(): ?string
    {
        return $this->keyProperty;
    }

    public function getKeyColumn(): ?string
    {
        return $this->keyColumn;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getFetchSize(): int
    {
        return $this->fetchSize;
    }

    public function getHydration(): ?Hydration
    {
        return $this->hydration;
    }

    public function getSqlSource(): ?SqlSource
    {
        return $this->sqlSource;
    }

    public function isFlushCache(): bool
    {
        return $this->flushCache;
    }

    public function isUseCache(): bool
    {
        return $this->useCache;
    }

    /**
     * Get the full qualified ID (namespace.id).
     */
    public function getFullId(): string
    {
        return $this->namespace . '.' . $this->id;
    }
}
