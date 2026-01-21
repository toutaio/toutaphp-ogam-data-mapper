<?php

declare(strict_types=1);

namespace Touta\Ogam\Plugin;

/**
 * Internal wrapper class for chaining interceptor invocations.
 *
 * This class is used by InterceptorChain to create a fake target object
 * that forwards method calls to the next interceptor in the chain.
 *
 * @internal
 */
final readonly class ChainedInvocationWrapper
{
    /**
     * @param InterceptorChain $chain The chain being executed
     * @param object $realTarget The actual target object
     * @param string $realMethod The actual method name
     * @param list<mixed> $realArgs The actual method arguments
     * @param int $nextIndex The index of the next interceptor
     */
    public function __construct(
        private InterceptorChain $chain,
        private object $realTarget,
        private string $realMethod,
        private array $realArgs,
        private int $nextIndex,
    ) {}

    /**
     * Handle any method call by forwarding to the next interceptor.
     *
     * @param string $name The method name (ignored, we use realMethod)
     * @param list<mixed> $arguments The method arguments (ignored, we use realArgs)
     *
     * @return mixed The result from the next interceptor in the chain
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->chain->invokeAt($this->realTarget, $this->realMethod, $this->realArgs, $this->nextIndex);
    }
}
