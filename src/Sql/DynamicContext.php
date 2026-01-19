<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql;

use ReflectionProperty;
use Touta\Ogam\Configuration;

/**
 * Context for dynamic SQL evaluation.
 *
 * Maintains state during SQL node evaluation.
 */
final class DynamicContext
{
    private string $sql = '';

    /** @var array<string, mixed> */
    private array $bindings = [];

    private int $uniqueNumber = 0;

    /** @var array<string, mixed>|object|null */
    private readonly array|object|null $parameter;

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    public function __construct(
        private readonly Configuration $configuration,
        array|object|null $parameter,
    ) {
        $this->parameter = $parameter;
    }

    public function appendSql(string $sql): void
    {
        $this->sql .= $sql;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function bind(string $name, mixed $value): void
    {
        $this->bindings[$name] = $value;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * @return array<string, mixed>|object|null
     */
    public function getParameter(): array|object|null
    {
        return $this->parameter;
    }

    /**
     * Get a unique number for variable naming.
     */
    public function getUniqueNumber(): int
    {
        return $this->uniqueNumber++;
    }

    /**
     * Evaluate an expression against the parameter.
     */
    public function evaluate(string $expression): mixed
    {
        return $this->getValueFromExpression($expression);
    }

    /**
     * Evaluate an expression as boolean.
     */
    public function evaluateBoolean(string $expression): bool
    {
        $value = $this->evaluate($expression);

        if (\is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (\is_string($value)) {
            return $value !== '';
        }

        if (\is_array($value)) {
            return $value !== [];
        }

        return (bool) $value;
    }

    private function getValueFromExpression(string $expression): mixed
    {
        $parameter = $this->parameter;

        if ($parameter === null) {
            return null;
        }

        // Check bindings first
        if (isset($this->bindings[$expression])) {
            return $this->bindings[$expression];
        }

        // Handle nested property access
        $parts = explode('.', $expression);
        $current = $parameter;

        foreach ($parts as $part) {
            if (\is_array($current)) {
                if (!\array_key_exists($part, $current)) {
                    return null;
                }
                $current = $current[$part];
            } elseif (\is_object($current)) {
                $current = $this->getObjectProperty($current, $part);
            } else {
                return null;
            }
        }

        return $current;
    }

    private function getObjectProperty(object $object, string $property): mixed
    {
        // Try getter
        $getter = 'get' . ucfirst($property);

        if (method_exists($object, $getter)) {
            return $object->{$getter}();
        }

        // Try boolean getter
        $isGetter = 'is' . ucfirst($property);

        if (method_exists($object, $isGetter)) {
            return $object->{$isGetter}();
        }

        // Try direct property
        if (property_exists($object, $property)) {
            $reflection = new ReflectionProperty($object, $property);
            $reflection->setAccessible(true);

            return $reflection->getValue($object);
        }

        return null;
    }
}
