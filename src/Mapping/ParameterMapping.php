<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Defines how a parameter is bound to a SQL statement.
 */
final class ParameterMapping
{
    /**
     * @param string $property The property name (e.g., 'id' or 'user.name')
     * @param string|null $phpType The PHP type for type handler selection
     * @param string|null $sqlType The SQL type (e.g., 'VARCHAR', 'INTEGER')
     * @param ParameterMode $mode Parameter mode for stored procedures
     * @param string|null $typeHandler The type handler class to use
     */
    public function __construct(
        private readonly string $property,
        private readonly ?string $phpType = null,
        private readonly ?string $sqlType = null,
        private readonly ParameterMode $mode = ParameterMode::IN,
        private readonly ?string $typeHandler = null,
    ) {}

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getPhpType(): ?string
    {
        return $this->phpType;
    }

    public function getSqlType(): ?string
    {
        return $this->sqlType;
    }

    public function getMode(): ParameterMode
    {
        return $this->mode;
    }

    public function getTypeHandler(): ?string
    {
        return $this->typeHandler;
    }

    public function isInputParameter(): bool
    {
        return $this->mode === ParameterMode::IN || $this->mode === ParameterMode::INOUT;
    }

    public function isOutputParameter(): bool
    {
        return $this->mode === ParameterMode::OUT || $this->mode === ParameterMode::INOUT;
    }
}
