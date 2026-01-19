<?php

declare(strict_types=1);

namespace Touta\Ogam\Contract;

use Touta\Ogam\Mapping\ResultMap;

/**
 * Hydrates database rows into PHP values.
 */
interface HydratorInterface
{
    /**
     * Hydrate a single row into a value.
     *
     * @param array<string, mixed> $row The database row
     * @param ResultMap|null $resultMap Optional result map for object hydration
     * @param string|null $resultType The target type (class name or primitive type)
     *
     * @return mixed The hydrated value
     */
    public function hydrate(array $row, ?ResultMap $resultMap, ?string $resultType): mixed;

    /**
     * Hydrate multiple rows into values.
     *
     * @param iterable<array<string, mixed>> $rows The database rows
     * @param ResultMap|null $resultMap Optional result map for object hydration
     * @param string|null $resultType The target type
     *
     * @return list<mixed> The hydrated values
     */
    public function hydrateAll(iterable $rows, ?ResultMap $resultMap, ?string $resultType): array;
}
