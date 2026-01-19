<?php

declare(strict_types=1);

namespace Touta\Ogam\Hydration;

use Touta\Ogam\Contract\HydratorInterface;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Type\TypeHandlerRegistry;

/**
 * Hydrates database rows as scalar values.
 *
 * Returns only the first column value from each row.
 * Useful for COUNT, SUM, or single-column queries.
 */
final class ScalarHydrator implements HydratorInterface
{
    public function __construct(
        private readonly TypeHandlerRegistry $typeHandlerRegistry,
    ) {}

    public function hydrate(array $row, ?ResultMap $resultMap, ?string $resultType): mixed
    {
        if ($row === []) {
            return null;
        }

        $value = \reset($row);

        if ($value === null || $resultType === null) {
            return $value;
        }

        // Convert to the requested type
        return $this->convertValue($value, $resultType);
    }

    public function hydrateAll(iterable $rows, ?ResultMap $resultMap, ?string $resultType): array
    {
        $results = [];

        foreach ($rows as $row) {
            $results[] = $this->hydrate($row, $resultMap, $resultType);
        }

        return $results;
    }

    private function convertValue(mixed $value, string $resultType): mixed
    {
        // Handle primitive types directly
        return match (\strtolower($resultType)) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            default => $this->convertViaHandler($value, $resultType),
        };
    }

    private function convertViaHandler(mixed $value, string $resultType): mixed
    {
        $handler = $this->typeHandlerRegistry->getHandler($resultType);
        $column = 'value';

        return $handler->getResult([$column => $value], $column);
    }
}
