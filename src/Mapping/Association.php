<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Defines a has-one (association) relationship mapping.
 *
 * Associations are loaded via nested results (JOINs), not nested selects.
 */
final class Association
{
    /**
     * @param string $property The target property name
     * @param string $javaType The associated object type (class name)
     * @param string|null $resultMapId The result map ID for the associated object
     * @param string $columnPrefix Column prefix for disambiguation
     * @param list<ResultMapping> $idMappings ID column mappings
     * @param list<ResultMapping> $resultMappings Regular column mappings
     */
    public function __construct(
        private readonly string $property,
        private readonly string $javaType,
        private readonly ?string $resultMapId = null,
        private readonly string $columnPrefix = '',
        private readonly array $idMappings = [],
        private readonly array $resultMappings = [],
    ) {}

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getJavaType(): string
    {
        return $this->javaType;
    }

    public function getResultMapId(): ?string
    {
        return $this->resultMapId;
    }

    public function getColumnPrefix(): string
    {
        return $this->columnPrefix;
    }

    /**
     * @return list<ResultMapping>
     */
    public function getIdMappings(): array
    {
        return $this->idMappings;
    }

    /**
     * @return list<ResultMapping>
     */
    public function getResultMappings(): array
    {
        return $this->resultMappings;
    }

    /**
     * @return list<ResultMapping>
     */
    public function getAllMappings(): array
    {
        return [...$this->idMappings, ...$this->resultMappings];
    }

    /**
     * Check if this association uses an external result map.
     */
    public function usesResultMap(): bool
    {
        return $this->resultMapId !== null;
    }
}
