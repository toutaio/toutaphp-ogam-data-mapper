<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Defines polymorphic result mapping based on a discriminator column.
 *
 * The discriminator column value determines which result map to use.
 */
final class Discriminator
{
    /**
     * @param string $column The discriminator column name
     * @param string|null $phpType The PHP type of the discriminator value
     * @param array<string, string> $cases Map of column value to result map ID
     */
    public function __construct(
        private readonly string $column,
        private readonly ?string $phpType = null,
        private readonly array $cases = [],
    ) {}

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getPhpType(): ?string
    {
        return $this->phpType;
    }

    /**
     * @return array<string, string>
     */
    public function getCases(): array
    {
        return $this->cases;
    }

    /**
     * Get the result map ID for a discriminator value.
     */
    public function getResultMapId(string $value): ?string
    {
        return $this->cases[$value] ?? null;
    }
}
