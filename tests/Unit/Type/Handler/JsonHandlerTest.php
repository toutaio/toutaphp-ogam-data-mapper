<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type\Handler;

use JsonException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Type\Handler\JsonHandler;

final class JsonHandlerTest extends TestCase
{
    private JsonHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new JsonHandler();
    }

    public function testGetPhpType(): void
    {
        $this->assertSame('array', $this->handler->getPhpType());
    }

    public function testSetParameterWithArray(): void
    {
        $data = ['name' => 'John', 'age' => 30];

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '{"name":"John","age":30}', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', $data, null);
    }

    public function testSetParameterWithEmptyArray(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '[]', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', [], null);
    }

    public function testSetParameterWithNestedArray(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                    'zip' => '10001',
                ],
            ],
        ];

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(
                ':test',
                '{"user":{"name":"John","address":{"city":"New York","zip":"10001"}}}',
                PDO::PARAM_STR,
            );

        $this->handler->setParameter($statement, ':test', $data, null);
    }

    public function testSetParameterWithNull(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', null, PDO::PARAM_NULL);

        $this->handler->setParameter($statement, ':test', null, null);
    }

    public function testSetParameterWithJsonString(): void
    {
        $json = '{"name":"John"}';

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '{"name":"John"}', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', $json, null);
    }

    public function testSetParameterWithObject(): void
    {
        $obj = new \stdClass();
        $obj->name = 'John';
        $obj->age = 30;

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '{"name":"John","age":30}', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', $obj, null);
    }

    public function testSetParameterWithUnicode(): void
    {
        $data = ['message' => 'こんにちは'];

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '{"message":"こんにちは"}', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', $data, null);
    }

    public function testSetParameterWithIntegerIndex(): void
    {
        $data = ['value' => 42];

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, '{"value":42}', PDO::PARAM_STR);

        $this->handler->setParameter($statement, 1, $data, null);
    }

    public function testGetResultWithJsonString(): void
    {
        $row = ['data' => '{"name":"John","age":30}'];
        $result = $this->handler->getResult($row, 'data');

        $this->assertIsArray($result);
        $this->assertSame(['name' => 'John', 'age' => 30], $result);
    }

    public function testGetResultWithEmptyJsonArray(): void
    {
        $row = ['data' => '[]'];
        $result = $this->handler->getResult($row, 'data');

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testGetResultWithEmptyJsonObject(): void
    {
        $row = ['data' => '{}'];
        $result = $this->handler->getResult($row, 'data');

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testGetResultWithNestedJson(): void
    {
        $row = ['data' => '{"user":{"name":"John","address":{"city":"New York"}}}'];
        $result = $this->handler->getResult($row, 'data');

        $this->assertIsArray($result);
        $this->assertSame('John', $result['user']['name']);
        $this->assertSame('New York', $result['user']['address']['city']);
    }

    public function testGetResultWithNull(): void
    {
        $row = ['data' => null];
        $result = $this->handler->getResult($row, 'data');

        $this->assertNull($result);
    }

    public function testGetResultWithMissingColumn(): void
    {
        $row = ['other' => '{}'];
        $result = $this->handler->getResult($row, 'data');

        $this->assertNull($result);
    }

    public function testGetResultWithAlreadyDecodedArray(): void
    {
        $data = ['name' => 'John'];
        $row = ['data' => $data];
        $result = $this->handler->getResult($row, 'data');

        $this->assertSame($data, $result);
    }

    public function testGetResultWithAlreadyDecodedObject(): void
    {
        $obj = new \stdClass();
        $obj->name = 'John';
        $row = ['data' => $obj];
        $result = $this->handler->getResult($row, 'data');

        $this->assertSame($obj, $result);
    }

    public function testGetResultWithNonStringNonArrayValue(): void
    {
        $row = ['data' => 42];
        $result = $this->handler->getResult($row, 'data');

        $this->assertSame(42, $result);
    }

    public function testGetResultByIndex(): void
    {
        $row = ['col1' => 'value', 'data' => '{"key":"value"}'];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertIsArray($result);
        $this->assertSame(['key' => 'value'], $result);
    }

    public function testGetResultByIndexWithNull(): void
    {
        $row = ['col1' => 'value', 'data' => null];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertNull($result);
    }

    public function testGetResultByIndexOutOfBounds(): void
    {
        $row = ['data' => '{}'];
        $result = $this->handler->getResultByIndex($row, 5);

        $this->assertNull($result);
    }

    public function testInvalidJsonThrowsException(): void
    {
        $this->expectException(JsonException::class);

        $row = ['data' => 'invalid json'];
        $this->handler->getResult($row, 'data');
    }

    public function testNonAssociativeDecoding(): void
    {
        $handler = new JsonHandler(associative: false);
        $row = ['data' => '{"name":"John"}'];
        $result = $handler->getResult($row, 'data');

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('John', $result->name);
    }

    public function testCustomDepth(): void
    {
        $handler = new JsonHandler(depth: 3);

        $this->assertInstanceOf(JsonHandler::class, $handler);
    }

    public function testDepthLessThanOneDefaultsTo512(): void
    {
        $handler = new JsonHandler(depth: 0);

        $deepJson = str_repeat('{"a":', 100) . '"value"' . str_repeat('}', 100);
        $row = ['data' => $deepJson];
        $result = $handler->getResult($row, 'data');

        $this->assertIsArray($result);
    }
}
