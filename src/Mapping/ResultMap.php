<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Defines how to map result set columns to object properties.
 *
 * A ResultMap can include:
 * - ID mappings (primary key columns)
 * - Result mappings (regular columns)
 * - Associations (has-one relationships)
 * - Collections (has-many relationships)
 * - Discriminator (for polymorphic mapping)
 */
final class ResultMap
{
    /**
     * @param string $id The result map ID
     * @param string $type The target class name
     * @param list<ResultMapping> $idMappings Primary key column mappings
     * @param list<ResultMapping> $resultMappings Regular column mappings
     * @param list<Association> $associations Has-one relationship mappings
     * @param list<Collection> $collections Has-many relationship mappings
     * @param Discriminator|null $discriminator Polymorphic mapping discriminator
     * @param bool $autoMapping Whether to auto-map unmapped columns
     * @param string|null $extends ID of parent result map to extend
     */
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly array $idMappings = [],
        private readonly array $resultMappings = [],
        private readonly array $associations = [],
        private readonly array $collections = [],
        private readonly ?Discriminator $discriminator = null,
        private readonly bool $autoMapping = true,
        private readonly ?string $extends = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
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
     * @return list<Association>
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }

    /**
     * @return list<Collection>
     */
    public function getCollections(): array
    {
        return $this->collections;
    }

    public function getDiscriminator(): ?Discriminator
    {
        return $this->discriminator;
    }

    public function isAutoMapping(): bool
    {
        return $this->autoMapping;
    }

    public function getExtends(): ?string
    {
        return $this->extends;
    }

    /**
     * Check if this result map has any nested mappings.
     */
    public function hasNestedMappings(): bool
    {
        return $this->associations !== [] || $this->collections !== [];
    }
}
