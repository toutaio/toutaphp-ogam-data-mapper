<?php

declare(strict_types=1);

namespace Touta\Ogam\Plugin;

/**
 * Interface for interceptors that can intercept method invocations.
 *
 * Interceptors provide AOP-style cross-cutting concerns like logging,
 * caching, timing, and auditing.
 */
interface Interceptor
{
    /**
     * Intercept a method invocation.
     *
     * @param Invocation $invocation The method invocation to intercept
     *
     * @return mixed The result of the intercepted method (or a modified result)
     */
    public function intercept(Invocation $invocation): mixed;
}
