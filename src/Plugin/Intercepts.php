<?php

declare(strict_types=1);

namespace Touta\Ogam\Plugin;

use Attribute;

/**
 * Attribute to declare which method signatures an interceptor targets.
 *
 * Usage:
 * ```php
 * #[Intercepts([
 *     new Signature(Executor::class, 'query', [MappedStatement::class, 'object']),
 *     new Signature(Executor::class, 'update', [MappedStatement::class, 'object']),
 * ])]
 * final class SlowQueryLogger implements Interceptor
 * {
 *     // ...
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Intercepts
{
    /**
     * @param list<Signature> $signatures The method signatures to intercept
     */
    public function __construct(
        public array $signatures,
    ) {}

    /**
     * Check if any signature matches the given target and method.
     *
     * @param class-string|object $target The target class name or instance
     * @param string $methodName The method name
     */
    public function matches(string|object $target, string $methodName): bool
    {
        foreach ($this->signatures as $signature) {
            if ($signature->matches($target, $methodName)) {
                return true;
            }
        }

        return false;
    }
}
