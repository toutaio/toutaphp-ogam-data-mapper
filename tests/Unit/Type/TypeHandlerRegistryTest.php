<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Type\Handler\BooleanHandler;
use Touta\Ogam\Type\Handler\DateTimeHandler;
use Touta\Ogam\Type\Handler\FloatHandler;
use Touta\Ogam\Type\Handler\IntegerHandler;
use Touta\Ogam\Type\Handler\JsonHandler;
use Touta\Ogam\Type\Handler\StringHandler;
use Touta\Ogam\Type\TypeHandlerRegistry;

final class TypeHandlerRegistryTest extends TestCase
{
    private TypeHandlerRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new TypeHandlerRegistry();
    }

    public function testRegistersDefaultHandlers(): void
    {
        $this->assertInstanceOf(IntegerHandler::class, $this->registry->getHandler('int'));
        $this->assertInstanceOf(IntegerHandler::class, $this->registry->getHandler('integer'));
        $this->assertInstanceOf(FloatHandler::class, $this->registry->getHandler('float'));
        $this->assertInstanceOf(FloatHandler::class, $this->registry->getHandler('double'));
        $this->assertInstanceOf(StringHandler::class, $this->registry->getHandler('string'));
        $this->assertInstanceOf(BooleanHandler::class, $this->registry->getHandler('bool'));
        $this->assertInstanceOf(BooleanHandler::class, $this->registry->getHandler('boolean'));
    }

    public function testRegistersDateTimeHandlers(): void
    {
        $this->assertInstanceOf(DateTimeHandler::class, $this->registry->getHandler(\DateTime::class));
        $this->assertInstanceOf(DateTimeHandler::class, $this->registry->getHandler(\DateTimeInterface::class));
    }

    public function testRegistersJsonHandler(): void
    {
        $this->assertInstanceOf(JsonHandler::class, $this->registry->getHandler('json'));
        $this->assertInstanceOf(JsonHandler::class, $this->registry->getHandler('array'));
    }

    public function testHandlerIsCaseInsensitive(): void
    {
        $handler1 = $this->registry->getHandler('STRING');
        $handler2 = $this->registry->getHandler('string');
        $handler3 = $this->registry->getHandler('String');

        $this->assertInstanceOf(StringHandler::class, $handler1);
        $this->assertInstanceOf(StringHandler::class, $handler2);
        $this->assertInstanceOf(StringHandler::class, $handler3);
    }

    public function testRegisterCustomHandler(): void
    {
        $customHandler = new StringHandler();
        $this->registry->register('custom', $customHandler);

        $this->assertSame($customHandler, $this->registry->getHandler('custom'));
    }

    public function testGetHandlerForValue(): void
    {
        $this->assertInstanceOf(IntegerHandler::class, $this->registry->getHandlerForValue(42));
        $this->assertInstanceOf(FloatHandler::class, $this->registry->getHandlerForValue(3.14));
        $this->assertInstanceOf(StringHandler::class, $this->registry->getHandlerForValue('hello'));
        $this->assertInstanceOf(BooleanHandler::class, $this->registry->getHandlerForValue(true));
    }

    public function testGetHandlerForDateTime(): void
    {
        $dateTime = new \DateTime();
        $dateTimeImmutable = new \DateTimeImmutable();

        $this->assertInstanceOf(DateTimeHandler::class, $this->registry->getHandlerForValue($dateTime));
    }

    public function testUnknownTypeReturnsUnknownHandler(): void
    {
        $handler = $this->registry->getHandler('unknown_type');

        // Default unknown handler is StringHandler
        $this->assertInstanceOf(StringHandler::class, $handler);
    }

    public function testHasHandler(): void
    {
        $this->assertTrue($this->registry->hasHandler('int'));
        $this->assertTrue($this->registry->hasHandler('string'));
        $this->assertFalse($this->registry->hasHandler('unknown_type'));
    }
}
