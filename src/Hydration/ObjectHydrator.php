<?php

declare(strict_types=1);

namespace Touta\Ogam\Hydration;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\HydratorInterface;
use Touta\Ogam\Mapping\Association;
use Touta\Ogam\Mapping\Collection;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Type\TypeHandlerRegistry;

/**
 * Hydrates database rows into PHP objects.
 *
 * Uses constructor-based hydration when possible for performance.
 * Falls back to setter-based hydration for mutable objects.
 * Supports nested result mapping for associations (has-one) and collections (has-many).
 */
final class ObjectHydrator implements HydratorInterface
{
    /** @var array<string, ReflectionClass<object>> */
    private array $reflectionCache = [];

    /** @var array<string, array<string, ReflectionProperty>> */
    private array $propertyCache = [];

    /** @var array<string, array<string, string>> */
    private array $setterCache = [];

    /** @var array<string, list<ReflectionParameter>> */
    private array $constructorParamsCache = [];

    public function __construct(
        private readonly TypeHandlerRegistry $typeHandlerRegistry,
        private readonly bool $mapUnderscoreToCamelCase = false,
    ) {}

    public function hydrate(array $row, ?ResultMap $resultMap, ?string $resultType): mixed
    {
        if ($resultType === null && $resultMap === null) {
            return $row;
        }

        $type = $resultType ?? ($resultMap !== null ? $resultMap->getType() : null);

        if ($type === null || !class_exists($type)) {
            return $row;
        }

        // Map columns to properties
        $propertyValues = $this->mapRowToProperties($row, $resultMap);

        // Handle associations (has-one relationships)
        if ($resultMap !== null) {
            foreach ($resultMap->getAssociations() as $association) {
                $associatedObject = $this->hydrateAssociation($row, $association);
                $propertyValues[$association->getProperty()] = $associatedObject;
            }
        }

        /** @var class-string $type */
        return $this->createObject($type, $propertyValues);
    }

    public function hydrateAll(iterable $rows, ?ResultMap $resultMap, ?string $resultType): array
    {
        // If no nested mappings, use simple hydration
        if ($resultMap === null || !$resultMap->hasNestedMappings()) {
            $results = [];

            foreach ($rows as $row) {
                $results[] = $this->hydrate($row, $resultMap, $resultType);
            }

            return $results;
        }

        // Handle nested result mapping (with collections)
        return $this->hydrateWithNestedResults($rows, $resultMap, $resultType);
    }

    /**
     * Hydrate a single row using discriminator-based type resolution.
     *
     * The discriminator column value determines which result map to use,
     * enabling polymorphic mapping.
     *
     * @param array<string, mixed> $row
     */
    public function hydrateWithDiscriminator(
        array $row,
        ResultMap $resultMap,
        Configuration $configuration,
    ): mixed {
        $discriminator = $resultMap->getDiscriminator();

        if ($discriminator === null) {
            // No discriminator, use regular hydration
            return $this->hydrate($row, $resultMap, $resultMap->getType());
        }

        // Get discriminator column value
        $column = $discriminator->getColumn();
        $discriminatorValue = $row[$column] ?? null;

        if ($discriminatorValue === null) {
            // Null discriminator, use base result map
            return $this->hydrate($row, $resultMap, $resultMap->getType());
        }

        // Get the result map ID for this discriminator value
        /** @var string|int|float|bool $discriminatorValue */
        $childResultMapId = $discriminator->getResultMapId((string) $discriminatorValue);

        if ($childResultMapId === null) {
            // No matching case, use base result map
            return $this->hydrate($row, $resultMap, $resultMap->getType());
        }

        // Get the child result map
        $childResultMap = $configuration->getResultMap($childResultMapId);

        if ($childResultMap === null) {
            // Child result map not found, use base result map
            return $this->hydrate($row, $resultMap, $resultMap->getType());
        }

        // Use the child result map for hydration
        return $this->hydrate($row, $childResultMap, $childResultMap->getType());
    }

    /**
     * Hydrate multiple rows using discriminator-based type resolution.
     *
     * @param iterable<array<string, mixed>> $rows
     *
     * @return list<mixed>
     */
    public function hydrateAllWithDiscriminator(
        iterable $rows,
        ResultMap $resultMap,
        Configuration $configuration,
    ): array {
        $results = [];

        foreach ($rows as $row) {
            $results[] = $this->hydrateWithDiscriminator($row, $resultMap, $configuration);
        }

        return $results;
    }

    /**
     * Hydrate rows with nested result mapping (associations and collections).
     *
     * Groups rows by parent ID and collects child items into collections.
     *
     * @param iterable<array<string, mixed>> $rows
     *
     * @return list<object>
     */
    private function hydrateWithNestedResults(iterable $rows, ResultMap $resultMap, ?string $resultType): array
    {
        $type = $resultType ?? $resultMap->getType();

        if (!class_exists($type)) {
            // Type doesn't exist, fall back to arrays (not returned as this method returns objects)
            // This is a fallback that shouldn't normally happen
            return [];
        }

        // Group rows by parent ID
        $idMappings = $resultMap->getIdMappings();
        $collections = $resultMap->getCollections();

        /** @var array<string, array{object: object|null, collections: array<string, array<string, object>>}> */
        $parentMap = [];

        foreach ($rows as $row) {
            // Get parent ID
            $parentId = $this->extractId($row, $idMappings);

            if (!isset($parentMap[$parentId])) {
                // Create the parent object
                $parentMap[$parentId] = [
                    'object' => null,
                    'collections' => [],
                ];

                // Map base properties
                $propertyValues = $this->mapRowToProperties($row, $resultMap);

                // Handle associations (has-one relationships)
                foreach ($resultMap->getAssociations() as $association) {
                    $associatedObject = $this->hydrateAssociation($row, $association);
                    $propertyValues[$association->getProperty()] = $associatedObject;
                }

                // Initialize empty collections
                foreach ($collections as $collection) {
                    $propertyValues[$collection->getProperty()] = [];
                    $parentMap[$parentId]['collections'][$collection->getProperty()] = [];
                }

                /** @var class-string $type */
                $parentMap[$parentId]['object'] = $this->createObject($type, $propertyValues);
            }

            // Add collection items
            foreach ($collections as $collection) {
                $this->addCollectionItem(
                    $row,
                    $collection,
                    $parentMap[$parentId]['object'],
                    $parentMap[$parentId]['collections'][$collection->getProperty()],
                );
            }
        }

        $results = [];

        foreach ($parentMap as $entry) {
            if ($entry['object'] !== null) {
                $results[] = $entry['object'];
            }
        }

        return $results;
    }

    /**
     * Extract the ID value from a row based on ID mappings.
     *
     * @param array<string, mixed> $row
     * @param list<ResultMapping> $idMappings
     */
    private function extractId(array $row, array $idMappings): string
    {
        $idParts = [];

        foreach ($idMappings as $mapping) {
            $column = $mapping->getColumn();
            $value = $row[$column] ?? '';
            $idParts[] = \is_scalar($value) ? (string) $value : '';
        }

        $encoded = json_encode($idParts);

        if ($encoded === false) {
            throw new RuntimeException('Failed to encode ID parts.');
        }

        return $encoded;
    }

    /**
     * Hydrate an association (has-one relationship) from a row.
     *
     * @param array<string, mixed> $row
     */
    private function hydrateAssociation(array $row, Association $association): ?object
    {
        $idMappings = $association->getIdMappings();
        $resultMappings = $association->getResultMappings();
        $type = $association->getPhpType();

        if (!class_exists($type)) {
            return null;
        }

        // Check if the association is null (all ID columns are null)
        $allNull = true;

        foreach ($idMappings as $mapping) {
            $column = $mapping->getColumn();
            $value = $row[$column] ?? null;

            if ($value !== null) {
                $allNull = false;

                break;
            }
        }

        if ($allNull) {
            return null;
        }

        // Map association columns to properties
        $propertyValues = [];

        foreach ($idMappings as $mapping) {
            $column = $mapping->getColumn();

            if (\array_key_exists($column, $row)) {
                $value = $this->convertValue($row[$column], $mapping);
                $propertyValues[$mapping->getProperty()] = $value;
            }
        }

        foreach ($resultMappings as $mapping) {
            $column = $mapping->getColumn();

            if (\array_key_exists($column, $row)) {
                $value = $this->convertValue($row[$column], $mapping);
                $propertyValues[$mapping->getProperty()] = $value;
            }
        }

        /** @var class-string $type */
        return $this->createObject($type, $propertyValues);
    }

    /**
     * Add a collection item to a parent object.
     *
     * @param array<string, mixed> $row
     * @param array<string, object> $seenItems Track seen items to avoid duplicates
     */
    private function addCollectionItem(
        array $row,
        Collection $collection,
        ?object $parent,
        array &$seenItems,
    ): void {
        if ($parent === null) {
            return;
        }

        $idMappings = $collection->getIdMappings();
        $resultMappings = $collection->getResultMappings();
        $type = $collection->getOfType();

        if (!class_exists($type)) {
            return;
        }

        // Check if the collection item is null (all ID columns are null)
        $allNull = true;

        foreach ($idMappings as $mapping) {
            $column = $mapping->getColumn();
            $value = $row[$column] ?? null;

            if ($value !== null) {
                $allNull = false;

                break;
            }
        }

        if ($allNull) {
            return;
        }

        // Extract item ID to check for duplicates
        $itemId = $this->extractId($row, $idMappings);

        if (isset($seenItems[$itemId])) {
            return; // Already added this item
        }

        // Map collection item columns to properties
        $propertyValues = [];

        foreach ($idMappings as $mapping) {
            $column = $mapping->getColumn();

            if (\array_key_exists($column, $row)) {
                $value = $this->convertValue($row[$column], $mapping);
                $propertyValues[$mapping->getProperty()] = $value;
            }
        }

        foreach ($resultMappings as $mapping) {
            $column = $mapping->getColumn();

            if (\array_key_exists($column, $row)) {
                $value = $this->convertValue($row[$column], $mapping);
                $propertyValues[$mapping->getProperty()] = $value;
            }
        }

        /** @var class-string $type */
        $item = $this->createObject($type, $propertyValues);
        $seenItems[$itemId] = $item;

        // Add to the parent's collection property
        $property = $collection->getProperty();
        $this->addToCollection($parent, $property, $item);
    }

    /**
     * Add an item to an object's collection property.
     */
    private function addToCollection(object $parent, string $property, object $item): void
    {
        $reflection = $this->getReflectionClass($parent::class);

        // Try setter first (e.g., addPost from "posts", addClass from "classes")
        $baseName = $property;
        if ($baseName !== '' && substr($baseName, -1) === 's') {
            $baseName = substr($baseName, 0, -1);
        }
        $addMethod = 'add' . ucfirst($baseName);

        if (method_exists($parent, $addMethod)) {
            $parent->{$addMethod}($item);

            return;
        }

        // Try direct property access
        if ($reflection->hasProperty($property)) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $currentValue = $prop->getValue($parent);

            if (\is_array($currentValue)) {
                $currentValue[] = $item;
                $prop->setValue($parent, $currentValue);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function mapRowToProperties(array $row, ?ResultMap $resultMap): array
    {
        $propertyValues = [];

        if ($resultMap !== null) {
            // Use explicit mappings
            foreach ($resultMap->getAllMappings() as $mapping) {
                $column = $mapping->getColumn();

                if (!\array_key_exists($column, $row)) {
                    continue;
                }

                $value = $this->convertValue($row[$column], $mapping);
                $propertyValues[$mapping->getProperty()] = $value;
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

                    $property = $this->columnToProperty($column);

                    if (!isset($propertyValues[$property])) {
                        $propertyValues[$property] = $value;
                    }
                }
            }
        } else {
            // No result map - auto-map all columns
            foreach ($row as $column => $value) {
                $property = $this->columnToProperty($column);
                $propertyValues[$property] = $value;
            }
        }

        return $propertyValues;
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

    private function columnToProperty(string $column): string
    {
        if (!$this->mapUnderscoreToCamelCase) {
            return $column;
        }

        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $column))));
    }

    /**
     * @param class-string $type
     * @param array<string, mixed> $propertyValues
     */
    private function createObject(string $type, array $propertyValues): object
    {
        $reflection = $this->getReflectionClass($type);

        // Try constructor-based hydration first
        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfParameters() > 0) {
            $object = $this->createViaConstructor($reflection, $constructor, $propertyValues);
        } else {
            $object = $reflection->newInstanceWithoutConstructor();
        }

        // Set remaining properties via setters or direct assignment
        $this->setRemainingProperties($object, $reflection, $propertyValues);

        return $object;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<string, mixed> $propertyValues
     */
    private function createViaConstructor(
        ReflectionClass $reflection,
        ReflectionMethod $constructor,
        array &$propertyValues,
    ): object {
        $params = $this->getConstructorParams($reflection);
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();

            if (\array_key_exists($name, $propertyValues)) {
                $args[] = $propertyValues[$name];
                unset($propertyValues[$name]);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                throw new RuntimeException(
                    \sprintf(
                        'Cannot hydrate %s: missing required constructor parameter "%s"',
                        $reflection->getName(),
                        $name,
                    ),
                );
            }
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<string, mixed> $propertyValues
     */
    private function setRemainingProperties(
        object $object,
        ReflectionClass $reflection,
        array $propertyValues,
    ): void {
        $properties = $this->getProperties($reflection);
        $setters = $this->getSetters($reflection);

        foreach ($propertyValues as $property => $value) {
            // Try setter first
            if (isset($setters[$property])) {
                $object->{$setters[$property]}($value);

                continue;
            }

            // Try direct property access
            if (isset($properties[$property])) {
                $prop = $properties[$property];

                if (!$prop->isReadOnly()) {
                    $prop->setAccessible(true);
                    $prop->setValue($object, $value);
                }
            }
        }
    }

    /**
     * @param class-string $type
     *
     * @return ReflectionClass<object>
     */
    private function getReflectionClass(string $type): ReflectionClass
    {
        if (!isset($this->reflectionCache[$type])) {
            $this->reflectionCache[$type] = new ReflectionClass($type);
        }

        return $this->reflectionCache[$type];
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, ReflectionProperty>
     */
    private function getProperties(ReflectionClass $reflection): array
    {
        $className = $reflection->getName();

        if (!isset($this->propertyCache[$className])) {
            $this->propertyCache[$className] = [];

            foreach ($reflection->getProperties() as $prop) {
                $this->propertyCache[$className][$prop->getName()] = $prop;
            }
        }

        return $this->propertyCache[$className];
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, string>
     */
    private function getSetters(ReflectionClass $reflection): array
    {
        $className = $reflection->getName();

        if (!isset($this->setterCache[$className])) {
            $this->setterCache[$className] = [];

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();

                if (str_starts_with($name, 'set') && $method->getNumberOfRequiredParameters() === 1) {
                    $property = lcfirst(substr($name, 3));
                    $this->setterCache[$className][$property] = $name;
                }
            }
        }

        return $this->setterCache[$className];
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<ReflectionParameter>
     */
    private function getConstructorParams(ReflectionClass $reflection): array
    {
        $className = $reflection->getName();

        if (!isset($this->constructorParamsCache[$className])) {
            $constructor = $reflection->getConstructor();
            $this->constructorParamsCache[$className] = $constructor?->getParameters() ?? [];
        }

        return $this->constructorParamsCache[$className];
    }
}
