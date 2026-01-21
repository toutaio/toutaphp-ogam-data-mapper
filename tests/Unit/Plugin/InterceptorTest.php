<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Throwable;
use Touta\Ogam\Plugin\Interceptor;
use Touta\Ogam\Plugin\InterceptorChain;
use Touta\Ogam\Plugin\Intercepts;
use Touta\Ogam\Plugin\Invocation;
use Touta\Ogam\Plugin\Signature;

/**
 * TDD Tests for Plugin/Interceptor System.
 *
 * The interceptor system allows for AOP-style cross-cutting concerns:
 * - Logging
 * - Caching
 * - Timing/profiling
 * - Auditing
 */
final class InterceptorTest extends TestCase
{
    // ========================================================================
    // Invocation Tests
    // ========================================================================

    public function testInvocationCanProceed(): void
    {
        $target = new class {
            public function greet(string $name): string
            {
                return "Hello, {$name}!";
            }
        };

        $invocation = new Invocation($target, 'greet', ['World']);

        $this->assertSame('Hello, World!', $invocation->proceed());
    }

    public function testInvocationExposesParts(): void
    {
        $target = new stdClass();
        $invocation = new Invocation($target, 'someMethod', ['arg1', 'arg2']);

        $this->assertSame($target, $invocation->target);
        $this->assertSame('someMethod', $invocation->method);
        $this->assertSame(['arg1', 'arg2'], $invocation->args);
    }

    public function testInvocationHandlesNoArguments(): void
    {
        $target = new class {
            public function noArgs(): string
            {
                return 'no args';
            }
        };

        $invocation = new Invocation($target, 'noArgs', []);

        $this->assertSame('no args', $invocation->proceed());
    }

    public function testInvocationHandlesVoidReturn(): void
    {
        $target = new class {
            public bool $called = false;

            public function doSomething(): void
            {
                $this->called = true;
            }
        };

        $invocation = new Invocation($target, 'doSomething', []);
        $result = $invocation->proceed();

        $this->assertNull($result);
        $this->assertTrue($target->called);
    }

    // ========================================================================
    // Signature Tests
    // ========================================================================

    public function testSignatureMatchesExactMethod(): void
    {
        $signature = new Signature(
            type: 'SomeClass',
            method: 'execute',
            args: ['string', 'int'],
        );

        $this->assertSame('SomeClass', $signature->type);
        $this->assertSame('execute', $signature->method);
        $this->assertSame(['string', 'int'], $signature->args);
    }

    public function testSignatureCanMatchAnyMethod(): void
    {
        $signature = new Signature(
            type: 'SomeClass',
            method: '*', // Wildcard - matches any method
            args: [],
        );

        $this->assertSame('*', $signature->method);
        $this->assertTrue($signature->matchesMethod('anyMethod'));
        $this->assertTrue($signature->matchesMethod('anotherMethod'));
    }

    public function testSignatureMatchesSpecificMethod(): void
    {
        $signature = new Signature(
            type: 'SomeClass',
            method: 'execute',
            args: [],
        );

        $this->assertTrue($signature->matchesMethod('execute'));
        $this->assertFalse($signature->matchesMethod('other'));
    }

    // ========================================================================
    // Intercepts Attribute Tests
    // ========================================================================

    public function testInterceptsAttributeHoldsSignatures(): void
    {
        $intercepts = new Intercepts([
            new Signature('TypeA', 'methodA', []),
            new Signature('TypeB', 'methodB', ['string']),
        ]);

        $this->assertCount(2, $intercepts->signatures);
        $this->assertSame('TypeA', $intercepts->signatures[0]->type);
        $this->assertSame('TypeB', $intercepts->signatures[1]->type);
    }

    public function testInterceptsAttributeCanBeReadFromClass(): void
    {
        $reflection = new ReflectionClass(SampleInterceptor::class);
        $attributes = $reflection->getAttributes(Intercepts::class);

        $this->assertCount(1, $attributes);

        $intercepts = $attributes[0]->newInstance();
        $this->assertCount(1, $intercepts->signatures);
        $this->assertSame('TestTarget', $intercepts->signatures[0]->type);
    }

    // ========================================================================
    // Interceptor Interface Tests
    // ========================================================================

    public function testInterceptorCanModifyResult(): void
    {
        $target = new class {
            public function getValue(): int
            {
                return 10;
            }
        };

        $interceptor = new class implements Interceptor {
            public function intercept(Invocation $invocation): mixed
            {
                $result = $invocation->proceed();

                return $result * 2; // Double the result
            }
        };

        $invocation = new Invocation($target, 'getValue', []);
        $result = $interceptor->intercept($invocation);

        $this->assertSame(20, $result);
    }

    public function testInterceptorCanPreventExecution(): void
    {
        $target = new class {
            public bool $called = false;

            public function dangerousMethod(): string
            {
                $this->called = true;

                return 'executed';
            }
        };

        $interceptor = new class implements Interceptor {
            public function intercept(Invocation $invocation): mixed
            {
                // Don't call proceed(), return cached/default value
                return 'blocked';
            }
        };

        $invocation = new Invocation($target, 'dangerousMethod', []);
        $result = $interceptor->intercept($invocation);

        $this->assertSame('blocked', $result);
        $this->assertFalse($target->called);
    }

    public function testInterceptorCanAccessArguments(): void
    {
        $target = new class {
            public function process(string $data): string
            {
                return $data;
            }
        };

        $interceptor = new class implements Interceptor {
            public function intercept(Invocation $invocation): mixed
            {
                // Modify arguments before proceeding
                $args = $invocation->args;
                $args[0] = strtoupper($args[0]);

                $modifiedInvocation = new Invocation(
                    $invocation->target,
                    $invocation->method,
                    $args,
                );

                return $modifiedInvocation->proceed();
            }
        };

        $invocation = new Invocation($target, 'process', ['hello']);
        $result = $interceptor->intercept($invocation);

        $this->assertSame('HELLO', $result);
    }

    public function testInterceptorCanHandleExceptions(): void
    {
        $target = new class {
            public function failingMethod(): never
            {
                throw new RuntimeException('Original error');
            }
        };

        $interceptor = new class implements Interceptor {
            public function intercept(Invocation $invocation): mixed
            {
                try {
                    return $invocation->proceed();
                } catch (Throwable $e) {
                    return 'handled: ' . $e->getMessage();
                }
            }
        };

        $invocation = new Invocation($target, 'failingMethod', []);
        $result = $interceptor->intercept($invocation);

        $this->assertSame('handled: Original error', $result);
    }

    // ========================================================================
    // Interceptor Chain Tests
    // ========================================================================

    public function testInterceptorChainWithNoInterceptors(): void
    {
        $target = new class {
            public function getValue(): int
            {
                return 42;
            }
        };

        $chain = new InterceptorChain([]);
        $result = $chain->invoke($target, 'getValue', []);

        $this->assertSame(42, $result);
    }

    public function testInterceptorChainWithSingleInterceptor(): void
    {
        $target = new class {
            public function getValue(): int
            {
                return 10;
            }
        };

        $interceptor = new class implements Interceptor {
            public function intercept(Invocation $invocation): mixed
            {
                return $invocation->proceed() + 5;
            }
        };

        $chain = new InterceptorChain([$interceptor]);
        $result = $chain->invoke($target, 'getValue', []);

        $this->assertSame(15, $result);
    }

    public function testInterceptorChainWithMultipleInterceptors(): void
    {
        $target = new class {
            public function getValue(): int
            {
                return 1;
            }
        };

        $doubler = new class implements Interceptor {
            public function intercept(Invocation $invocation): mixed
            {
                return $invocation->proceed() * 2;
            }
        };

        $adder = new class implements Interceptor {
            public function intercept(Invocation $invocation): mixed
            {
                return $invocation->proceed() + 10;
            }
        };

        // Order: adder runs first (outer), doubler runs second (inner)
        // So: adder(doubler(1)) = adder(2) = 12
        $chain = new InterceptorChain([$adder, $doubler]);
        $result = $chain->invoke($target, 'getValue', []);

        $this->assertSame(12, $result);
    }

    public function testInterceptorChainPreservesExecutionOrder(): void
    {
        $order = [];

        $target = new class {
            public function action(): string
            {
                return 'done';
            }
        };

        $first = new class ($order) implements Interceptor {
            public function __construct(private array &$order) {}

            public function intercept(Invocation $invocation): mixed
            {
                $this->order[] = 'first-before';
                $result = $invocation->proceed();
                $this->order[] = 'first-after';

                return $result;
            }
        };

        $second = new class ($order) implements Interceptor {
            public function __construct(private array &$order) {}

            public function intercept(Invocation $invocation): mixed
            {
                $this->order[] = 'second-before';
                $result = $invocation->proceed();
                $this->order[] = 'second-after';

                return $result;
            }
        };

        $chain = new InterceptorChain([$first, $second]);
        $chain->invoke($target, 'action', []);

        $this->assertSame([
            'first-before',
            'second-before',
            'second-after',
            'first-after',
        ], $order);
    }

    public function testInterceptorChainHandlesExceptions(): void
    {
        $target = new class {
            public function fail(): never
            {
                throw new RuntimeException('Boom!');
            }
        };

        $logger = new class implements Interceptor {
            public ?string $lastError = null;

            public function intercept(Invocation $invocation): mixed
            {
                try {
                    return $invocation->proceed();
                } catch (Throwable $e) {
                    $this->lastError = $e->getMessage();

                    throw $e;
                }
            }
        };

        $chain = new InterceptorChain([$logger]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Boom!');

        try {
            $chain->invoke($target, 'fail', []);
        } finally {
            $this->assertSame('Boom!', $logger->lastError);
        }
    }

    // ========================================================================
    // Real-world Example: Timing Interceptor
    // ========================================================================

    public function testTimingInterceptorExample(): void
    {
        $target = new class {
            public function slowMethod(): string
            {
                usleep(1000); // 1ms

                return 'completed';
            }
        };

        $timingInterceptor = new class implements Interceptor {
            public float $elapsedMs = 0;

            public function intercept(Invocation $invocation): mixed
            {
                $start = hrtime(true);

                try {
                    return $invocation->proceed();
                } finally {
                    $this->elapsedMs = (hrtime(true) - $start) / 1_000_000;
                }
            }
        };

        $chain = new InterceptorChain([$timingInterceptor]);
        $result = $chain->invoke($target, 'slowMethod', []);

        $this->assertSame('completed', $result);
        $this->assertGreaterThan(0.5, $timingInterceptor->elapsedMs);
    }
}

// Sample interceptor class for attribute testing
#[Intercepts([
    new Signature('TestTarget', 'execute', ['string']),
])]
final class SampleInterceptor implements Interceptor
{
    public function intercept(Invocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}
