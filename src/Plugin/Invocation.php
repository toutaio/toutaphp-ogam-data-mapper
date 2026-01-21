<?php

declare(strict_types=1);

namespace Touta\Ogam\Plugin;

/**
 * Represents a method invocation that can be intercepted.
 *
 * Provides access to the target object, method name, and arguments,
 * and allows the interceptor to proceed with the actual method call.
 */
final readonly class Invocation
{
    /**
     * @param list<mixed> $args
     */
    public function __construct(
        public object $target,
        public string $method,
        public array $args,
    ) {}

    /**
     * Proceed with the actual method invocation.
     */
    public function proceed(): mixed
    {
        return ($this->target)->{$this->method}(...$this->args);
    }
}
