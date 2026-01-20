<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Type\BaseTypeHandler;

final class BaseTypeHandlerTest extends TestCase
{
    private BaseTypeHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new class() extends BaseTypeHandler {
            public function getPhpType(): string
            {
                return 'test';
            }

            protected function setNonNullParameter(
                PDOStatement $statement,
                int|string $index,
                mixed $value,
                ?string $sqlType,
            ): void {
                $statement->bindValue($index, $value, PDO::PARAM_STR);
            }

            protected function getNonNullResult(mixed $value): mixed
            {
                return 'processed:' . $value;
            }
        };
    }

    public function testSetParameterWithNullBindsNull(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', null, PDO::PARAM_NULL);

        $this->handler->setParameter($statement, ':test', null, null);
    }

    public function testSetParameterWithValueCallsNonNullMethod(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 'value', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', 'value', null);
    }

    public function testSetParameterWithIntegerIndex(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, 'value', PDO::PARAM_STR);

        $this->handler->setParameter($statement, 1, 'value', null);
    }

    public function testGetResultWithNullValueReturnsNull(): void
    {
        $row = ['column' => null];
        $result = $this->handler->getResult($row, 'column');

        $this->assertNull($result);
    }

    public function testGetResultWithMissingColumnReturnsNull(): void
    {
        $row = ['other_column' => 'value'];
        $result = $this->handler->getResult($row, 'column');

        $this->assertNull($result);
    }

    public function testGetResultWithValueCallsNonNullMethod(): void
    {
        $row = ['column' => 'test_value'];
        $result = $this->handler->getResult($row, 'column');

        $this->assertSame('processed:test_value', $result);
    }

    public function testGetResultByIndexWithNullValueReturnsNull(): void
    {
        $row = ['col1' => 'a', 'col2' => null];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertNull($result);
    }

    public function testGetResultByIndexOutOfBoundsReturnsNull(): void
    {
        $row = ['col1' => 'value'];
        $result = $this->handler->getResultByIndex($row, 10);

        $this->assertNull($result);
    }

    public function testGetResultByIndexWithValueCallsNonNullMethod(): void
    {
        $row = ['col1' => 'first', 'col2' => 'second'];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertSame('processed:second', $result);
    }

    public function testGetResultByIndexWithFirstIndex(): void
    {
        $row = ['col1' => 'first', 'col2' => 'second'];
        $result = $this->handler->getResultByIndex($row, 0);

        $this->assertSame('processed:first', $result);
    }

    public function testGetResultByIndexIgnoresKeyNames(): void
    {
        $row = ['named_key' => 'value1', 'another_key' => 'value2'];
        $result = $this->handler->getResultByIndex($row, 0);

        $this->assertSame('processed:value1', $result);
    }

    public function testGetResultWithEmptyColumnName(): void
    {
        $row = ['' => 'value'];
        $result = $this->handler->getResult($row, '');

        $this->assertSame('processed:value', $result);
    }

    public function testGetResultByIndexWithEmptyRow(): void
    {
        $row = [];
        $result = $this->handler->getResultByIndex($row, 0);

        $this->assertNull($result);
    }
}
