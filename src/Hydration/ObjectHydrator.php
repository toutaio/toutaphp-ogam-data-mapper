<?php

declare(strict_types=1);

namespace Touta\Ogam\Hydration;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;
use Touta\Ogam\Contract\HydratorInterface;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Type\TypeHandlerRegistry;

/**
 * Hydrates database rows into PHP objects.
 *
 * Uses constructor-based hydration when possible for performance.
 * Falls back to setter-based hydration for mutable objects.
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

        /** @var class-string $type */
        return $this->createObject($type, $propertyValues);
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
