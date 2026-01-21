<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql;

use ReflectionObject;
use ReflectionProperty;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\Expression\ExpressionEvaluator;

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

    private readonly ExpressionEvaluator $expressionEvaluator;

    /**
     * @param array<string, mixed>|object|null $parameter
     */
    public function __construct(
        private readonly Configuration $configuration,
        array|object|null $parameter,
        ?ExpressionEvaluator $expressionEvaluator = null,
    ) {
        $this->parameter = $parameter;
        $this->expressionEvaluator = $expressionEvaluator ?? new ExpressionEvaluator();
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
     *
     * Supports both simple property access (e.g., "name", "user.email")
     * and complex expressions (e.g., "name !== null", "age > 18").
     */
    public function evaluate(string $expression): mixed
    {
        return $this->expressionEvaluator->evaluate($expression, $this->getEvaluationBindings());
    }

    /**
     * Evaluate an expression as boolean.
     *
     * Supports both simple property access and complex expressions.
     */
    public function evaluateBoolean(string $expression): bool
    {
        return $this->expressionEvaluator->evaluateBoolean($expression, $this->getEvaluationBindings());
    }

    /**
     * Get the combined bindings for expression evaluation.
     *
     * Merges the parameter (if array) with explicit bindings.
     * Explicit bindings take precedence.
     *
     * @return array<string, mixed>
     */
    private function getEvaluationBindings(): array
    {
        $parameterBindings = [];

        if (\is_array($this->parameter)) {
            $parameterBindings = $this->parameter;
        } elseif (\is_object($this->parameter)) {
            // For objects, wrap in a binding so property access works
            // The evaluator handles object property access internally
            $parameterBindings = $this->extractObjectProperties($this->parameter);
        }

        // Explicit bindings take precedence
        return array_merge($parameterBindings, $this->bindings);
    }

    /**
     * Extract public properties from an object for evaluation.
     *
     * @return array<string, mixed>
     */
    private function extractObjectProperties(object $object): array
    {
        $properties = [];
        $reflection = new ReflectionObject($object);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $properties[$property->getName()] = $property->getValue($object);
        }

        // Also include getter-based properties via the object itself
        // The ExpressionEvaluator handles this for nested access
        return $properties;
    }
}
