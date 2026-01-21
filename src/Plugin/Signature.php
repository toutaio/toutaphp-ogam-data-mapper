<?php

declare(strict_types=1);

namespace Touta\Ogam\Plugin;

/**
 * Defines which methods an interceptor should intercept.
 *
 * A signature specifies:
 * - The target type (class/interface)
 * - The method name (or '*' for all methods)
 * - Expected argument types (optional, for documentation)
 */
final readonly class Signature
{
    /**
     * @param string $type The fully qualified class or interface name to intercept
     * @param string $method The method name, or '*' to match any method
     * @param list<string> $args The expected argument types (for documentation)
     */
    public function __construct(
        public string $type,
        public string $method,
        public array $args = [],
    ) {}

    /**
     * Check if this signature matches the given method name.
     */
    public function matchesMethod(string $methodName): bool
    {
        if ($this->method === '*') {
            return true;
        }

        return $this->method === $methodName;
    }

    /**
     * Check if this signature matches the given type.
     *
     * @param class-string|object $target The target class name or instance
     */
    public function matchesType(string|object $target): bool
    {
        $targetClass = \is_object($target) ? $target::class : $target;

        return $targetClass === $this->type
            || is_subclass_of($targetClass, $this->type)
            || (interface_exists($this->type) && is_a($targetClass, $this->type, true));
    }

    /**
     * Check if this signature matches both type and method.
     *
     * @param class-string|object $target The target class name or instance
     * @param string $methodName The method name
     */
    public function matches(string|object $target, string $methodName): bool
    {
        return $this->matchesType($target) && $this->matchesMethod($methodName);
    }
}
