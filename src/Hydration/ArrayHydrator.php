<?php

declare(strict_types=1);

namespace Touta\Ogam\Hydration;

use Touta\Ogam\Contract\HydratorInterface;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Type\TypeHandlerRegistry;

/**
 * Hydrates database rows as associative arrays.
 *
 * Faster than object hydration with lower memory usage.
 * Useful for read-only operations or when full objects aren't needed.
 */
final class ArrayHydrator implements HydratorInterface
{
    public function __construct(
        private readonly TypeHandlerRegistry $typeHandlerRegistry,
        private readonly bool $mapUnderscoreToCamelCase = false,
    ) {}

    public function hydrate(array $row, ?ResultMap $resultMap, ?string $resultType): array
    {
        if ($resultMap === null) {
            return $this->mapUnderscoreToCamelCase ? $this->convertKeys($row) : $row;
        }

        return $this->mapRowWithResultMap($row, $resultMap);
    }

    public function hydrateAll(iterable $rows, ?ResultMap $resultMap, ?string $resultType): array
    {
        $results = [];

        foreach ($rows as $row) {
            $results[] = $this->hydrate($row, $resultMap, $resultType);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function mapRowWithResultMap(array $row, ResultMap $resultMap): array
    {
        $result = [];

        // Apply explicit mappings
        foreach ($resultMap->getAllMappings() as $mapping) {
            $column = $mapping->getColumn();

            if (!\array_key_exists($column, $row)) {
                continue;
            }

            $value = $this->convertValue($row[$column], $mapping);
            $result[$mapping->getProperty()] = $value;
        }

        // Auto-map remaining columns if enabled
        if ($resultMap->isAutoMapping()) {
            $mappedColumns = array_map(
                static fn(ResultMapping $m) => $m->getColumn(),
                $resultMap->getAllMappings(),
            );

            foreach ($row as $column => $value) {
                if (\in_array($column, $mappedColumns, true)) {
                    continue;
                }

                $key = $this->columnToKey($column);

                if (!isset($result[$key])) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    private function convertValue(mixed $value, ResultMapping $mapping): mixed
    {
        if ($value === null) {
            return null;
        }

        $phpType = $mapping->getPhpType();

        if ($phpType === null) {
            return $value;
        }

        $handler = $this->typeHandlerRegistry->getHandler($phpType);

        return $handler->getResult([$mapping->getColumn() => $value], $mapping->getColumn());
    }

    private function columnToKey(string $column): string
    {
        if (!$this->mapUnderscoreToCamelCase) {
            return $column;
        }

        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $column))));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function convertKeys(array $row): array
    {
        $result = [];

        foreach ($row as $key => $value) {
            $result[$this->columnToKey($key)] = $value;
        }

        return $result;
    }
}
