<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql;

use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\ParameterMapping;

/**
 * Static SQL source for SQL without dynamic elements.
 *
 * Used when the SQL has no conditional or iterative elements.
 */
final class StaticSqlSource implements SqlSource
{
    /**
     * @param string $sql The SQL with positional placeholders
     * @param list<ParameterMapping> $parameterMappings The parameter mappings
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $parameterMappings = [],
    ) {}

    public function getBoundSql(array|object|null $parameter): BoundSql
    {
        return new BoundSql($this->sql, $this->parameterMappings);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return list<ParameterMapping>
     */
    public function getParameterMappings(): array
    {
        return $this->parameterMappings;
    }
}
