<?php

declare(strict_types=1);

namespace Touta\Ogam\Plugin;

/**
 * Chains multiple interceptors together in execution order.
 *
 * The chain creates a nested call structure where each interceptor
 * wraps the next one, allowing for "before" and "after" processing
 * around method execution.
 *
 * Execution order for chain [A, B]:
 * 1. A.intercept() starts
 * 2. A calls invocation.proceed()
 * 3. B.intercept() starts
 * 4. B calls invocation.proceed()
 * 5. Actual method executes
 * 6. B.intercept() completes
 * 7. A.intercept() completes
 */
final readonly class InterceptorChain
{
    /**
     * @param list<Interceptor> $interceptors The interceptors in order of execution
     */
    public function __construct(
        private array $interceptors,
    ) {}

    /**
     * Invoke the chain for a given target and method.
     *
     * @param object $target The target object
     * @param string $method The method name
     * @param list<mixed> $args The method arguments
     *
     * @return mixed The result of the method invocation
     */
    public function invoke(object $target, string $method, array $args): mixed
    {
        if ($this->interceptors === []) {
            return $target->{$method}(...$args);
        }

        $invocation = $this->buildChainedInvocation($target, $method, $args, 0);

        return $this->interceptors[0]->intercept($invocation);
    }

    /**
     * Invoke the chain starting at a specific index.
     *
     * @internal Used by the chained invocation wrapper
     *
     * @param object $target The target object
     * @param string $method The method name
     * @param list<mixed> $args The method arguments
     * @param int $index The interceptor index to start from
     *
     * @return mixed The result of the method invocation
     */
    public function invokeAt(object $target, string $method, array $args, int $index): mixed
    {
        if ($index >= \count($this->interceptors)) {
            return $target->{$method}(...$args);
        }

        $invocation = $this->buildChainedInvocation($target, $method, $args, $index);

        return $this->interceptors[$index]->intercept($invocation);
    }

    /**
     * Build a chained invocation that calls the next interceptor.
     *
     * @param object $target The target object
     * @param string $method The method name
     * @param list<mixed> $args The method arguments
     * @param int $index The current interceptor index
     */
    private function buildChainedInvocation(object $target, string $method, array $args, int $index): Invocation
    {
        $nextIndex = $index + 1;

        if ($nextIndex >= \count($this->interceptors)) {
            // Last interceptor - proceed calls the actual method
            return new Invocation($target, $method, $args);
        }

        // Create a wrapper target that calls the next interceptor
        $chain = $this;
        $wrapper = new ChainedInvocationWrapper($chain, $target, $method, $args, $nextIndex);

        return new Invocation($wrapper, $method, $args);
    }
}
