<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Represents a SQL statement with bound parameters.
 *
 * This is the final SQL after dynamic elements have been processed
 * and parameter placeholders have been identified.
 */
final class BoundSql
{
    /**
     * @param string $sql The SQL with parameter placeholders
     * @param list<ParameterMapping> $parameterMappings The parameter mappings
     * @param array<string, mixed> $additionalParameters Extra parameters added by dynamic SQL
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $parameterMappings = [],
        private array $additionalParameters = [],
    ) {}

    /**
     * Get the SQL with parameter placeholders.
     */
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

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalParameters(): array
    {
        return $this->additionalParameters;
    }

    /**
     * Check if an additional parameter exists.
     */
    public function hasAdditionalParameter(string $name): bool
    {
        return \array_key_exists($name, $this->additionalParameters);
    }

    /**
     * Get an additional parameter value.
     */
    public function getAdditionalParameter(string $name): mixed
    {
        return $this->additionalParameters[$name] ?? null;
    }

    /**
     * Set an additional parameter (used by dynamic SQL).
     */
    public function setAdditionalParameter(string $name, mixed $value): void
    {
        $this->additionalParameters[$name] = $value;
    }
}
