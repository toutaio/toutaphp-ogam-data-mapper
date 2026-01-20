<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type\Handler;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Type\Handler\BooleanHandler;

final class BooleanHandlerTest extends TestCase
{
    private BooleanHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new BooleanHandler();
    }

    public function testGetPhpType(): void
    {
        $this->assertSame('bool', $this->handler->getPhpType());
    }

    public function testSetParameterWithTrue(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', true, PDO::PARAM_BOOL);

        $this->handler->setParameter($statement, ':test', true, null);
    }

    public function testSetParameterWithFalse(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', false, PDO::PARAM_BOOL);

        $this->handler->setParameter($statement, ':test', false, null);
    }

    public function testSetParameterWithNull(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', null, PDO::PARAM_NULL);

        $this->handler->setParameter($statement, ':test', null, null);
    }

    public function testSetParameterWithIntegerIndex(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, true, PDO::PARAM_BOOL);

        $this->handler->setParameter($statement, 1, true, null);
    }

    public function testSetParameterWithNumericOne(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', true, PDO::PARAM_BOOL);

        $this->handler->setParameter($statement, ':test', 1, null);
    }

    public function testSetParameterWithNumericZero(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', false, PDO::PARAM_BOOL);

        $this->handler->setParameter($statement, ':test', 0, null);
    }

    public function testGetResultWithBooleanTrue(): void
    {
        $row = ['active' => true];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithBooleanFalse(): void
    {
        $row = ['active' => false];
        $result = $this->handler->getResult($row, 'active');

        $this->assertFalse($result);
    }

    public function testGetResultWithIntegerOne(): void
    {
        $row = ['active' => 1];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithIntegerZero(): void
    {
        $row = ['active' => 0];
        $result = $this->handler->getResult($row, 'active');

        $this->assertFalse($result);
    }

    public function testGetResultWithStringTrue(): void
    {
        $row = ['active' => 'true'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithStringFalse(): void
    {
        $row = ['active' => 'false'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertFalse($result);
    }

    public function testGetResultWithStringYes(): void
    {
        $row = ['active' => 'yes'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithStringNo(): void
    {
        $row = ['active' => 'no'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertFalse($result);
    }

    public function testGetResultWithStringOne(): void
    {
        $row = ['active' => '1'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithStringZero(): void
    {
        $row = ['active' => '0'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertFalse($result);
    }

    public function testGetResultWithStringT(): void
    {
        $row = ['active' => 't'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithStringY(): void
    {
        $row = ['active' => 'y'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithStringOn(): void
    {
        $row = ['active' => 'on'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithStringOff(): void
    {
        $row = ['active' => 'off'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertFalse($result);
    }

    public function testGetResultWithUppercaseTrue(): void
    {
        $row = ['active' => 'TRUE'];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithNull(): void
    {
        $row = ['active' => null];
        $result = $this->handler->getResult($row, 'active');

        $this->assertNull($result);
    }

    public function testGetResultWithMissingColumn(): void
    {
        $row = ['other' => true];
        $result = $this->handler->getResult($row, 'active');

        $this->assertNull($result);
    }

    public function testGetResultWithNumericNonZero(): void
    {
        $row = ['active' => 5];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultWithNegativeNumber(): void
    {
        $row = ['active' => -1];
        $result = $this->handler->getResult($row, 'active');

        $this->assertTrue($result);
    }

    public function testGetResultByIndex(): void
    {
        $row = ['col1' => 'value', 'active' => true];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertTrue($result);
    }

    public function testGetResultByIndexWithNull(): void
    {
        $row = ['col1' => 'value', 'active' => null];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertNull($result);
    }

    public function testGetResultByIndexOutOfBounds(): void
    {
        $row = ['active' => true];
        $result = $this->handler->getResultByIndex($row, 5);

        $this->assertNull($result);
    }
}
