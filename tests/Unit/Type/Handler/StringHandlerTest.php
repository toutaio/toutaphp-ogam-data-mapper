<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type\Handler;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Stringable;
use Touta\Ogam\Type\Handler\StringHandler;

final class StringHandlerTest extends TestCase
{
    private StringHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new StringHandler();
    }

    public function testGetPhpType(): void
    {
        $this->assertSame('string', $this->handler->getPhpType());
    }

    public function testSetParameterWithString(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 'hello world', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', 'hello world', null);
    }

    public function testSetParameterWithEmptyString(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', '', null);
    }

    public function testSetParameterWithNull(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', null, PDO::PARAM_NULL);

        $this->handler->setParameter($statement, ':test', null, null);
    }

    public function testSetParameterWithInteger(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '42', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', 42, null);
    }

    public function testSetParameterWithFloat(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '3.14', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', 3.14, null);
    }

    public function testSetParameterWithBoolean(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '1', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', true, null);
    }

    public function testSetParameterWithStringable(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable value';
            }
        };

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 'stringable value', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', $stringable, null);
    }

    public function testSetParameterWithNonScalarReturnsEmptyString(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', ['array'], null);
    }

    public function testSetParameterWithIntegerIndex(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, 'test', PDO::PARAM_STR);

        $this->handler->setParameter($statement, 1, 'test', null);
    }

    public function testGetResultWithString(): void
    {
        $row = ['name' => 'John Doe'];
        $result = $this->handler->getResult($row, 'name');

        $this->assertSame('John Doe', $result);
    }

    public function testGetResultWithEmptyString(): void
    {
        $row = ['name' => ''];
        $result = $this->handler->getResult($row, 'name');

        $this->assertSame('', $result);
    }

    public function testGetResultWithNull(): void
    {
        $row = ['name' => null];
        $result = $this->handler->getResult($row, 'name');

        $this->assertNull($result);
    }

    public function testGetResultWithMissingColumn(): void
    {
        $row = ['other' => 'value'];
        $result = $this->handler->getResult($row, 'name');

        $this->assertNull($result);
    }

    public function testGetResultWithInteger(): void
    {
        $row = ['name' => 123];
        $result = $this->handler->getResult($row, 'name');

        $this->assertSame('123', $result);
    }

    public function testGetResultWithFloat(): void
    {
        $row = ['name' => 99.99];
        $result = $this->handler->getResult($row, 'name');

        $this->assertSame('99.99', $result);
    }

    public function testGetResultWithBoolean(): void
    {
        $row = ['name' => true];
        $result = $this->handler->getResult($row, 'name');

        $this->assertSame('1', $result);
    }

    public function testGetResultWithStringable(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable result';
            }
        };

        $row = ['name' => $stringable];
        $result = $this->handler->getResult($row, 'name');

        $this->assertSame('stringable result', $result);
    }

    public function testGetResultWithNonScalarReturnsEmptyString(): void
    {
        $row = ['name' => ['array']];
        $result = $this->handler->getResult($row, 'name');

        $this->assertSame('', $result);
    }

    public function testGetResultByIndex(): void
    {
        $row = ['col1' => 'value1', 'col2' => 'value2'];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertSame('value2', $result);
    }

    public function testGetResultByIndexWithNull(): void
    {
        $row = ['col1' => 'value1', 'col2' => null];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertNull($result);
    }

    public function testGetResultByIndexOutOfBounds(): void
    {
        $row = ['col1' => 'value1'];
        $result = $this->handler->getResultByIndex($row, 5);

        $this->assertNull($result);
    }

    public function testGetResultWithUnicodeString(): void
    {
        $row = ['name' => 'こんにちは世界'];
        $result = $this->handler->getResult($row, 'name');

        $this->assertSame('こんにちは世界', $result);
    }
}
