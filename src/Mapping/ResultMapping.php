<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Maps a single column to an object property.
 */
final class ResultMapping
{
    /**
     * @param string $property The target property name
     * @param string $column The source column name
     * @param string|null $phpType The PHP type (for type handler selection)
     * @param string|null $typeHandler The type handler class to use
     */
    public function __construct(
        private readonly string $property,
        private readonly string $column,
        private readonly ?string $phpType = null,
        private readonly ?string $typeHandler = null,
    ) {}

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getPhpType(): ?string
    {
        return $this->phpType;
    }

    public function getTypeHandler(): ?string
    {
        return $this->typeHandler;
    }
}
