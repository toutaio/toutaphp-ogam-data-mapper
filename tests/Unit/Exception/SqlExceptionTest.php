<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Exception;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Exception\OgamException;
use Touta\Ogam\Exception\SqlException;

#[CoversClass(SqlException::class)]
final class SqlExceptionTest extends TestCase
{
    private static function assertStringContains(string $needle, string $haystack): void
    {
        self::assertStringContainsString($needle, $haystack);
    }

    #[Test]
    public function extendsOgamException(): void
    {
        $exception = new SqlException('Test error');

        $this->assertInstanceOf(OgamException::class, $exception);
    }

    #[Test]
    public function canBeCreatedWithMessage(): void
    {
        $exception = new SqlException('Query failed');

        $this->assertSame('Query failed', $exception->getMessage());
    }

    #[Test]
    public function canWrapPdoException(): void
    {
        $pdoException = new PDOException('SQLSTATE[42S02]: Table not found');
        $exception = SqlException::fromPdoException($pdoException);

        $this->assertSame($pdoException, $exception->getPrevious());
        $this->assertStringContains('Table not found', $exception->getMessage());
    }

    #[Test]
    public function canIncludeSqlInException(): void
    {
        $pdoException = new PDOException('Syntax error');
        $sql = 'SELECT * FROM users WHERE id = ?';

        $exception = SqlException::fromPdoException($pdoException, $sql);

        $this->assertSame($sql, $exception->getSql());
        $this->assertStringContains($sql, $exception->getMessage());
    }

    #[Test]
    public function canIncludeParametersInException(): void
    {
        $pdoException = new PDOException('Constraint violation');
        $sql = 'INSERT INTO users (name) VALUES (?)';
        $parameters = ['John'];

        $exception = SqlException::fromPdoException($pdoException, $sql, $parameters);

        $this->assertSame($parameters, $exception->getParameters());
    }

    #[Test]
    public function getSqlReturnsNullWhenNotSet(): void
    {
        $exception = new SqlException('Error');

        $this->assertNull($exception->getSql());
    }

    #[Test]
    public function getParametersReturnsEmptyArrayWhenNotSet(): void
    {
        $exception = new SqlException('Error');

        $this->assertSame([], $exception->getParameters());
    }

    #[Test]
    public function formatsMessageWithSqlAndParameters(): void
    {
        $pdoException = new PDOException('Error');
        $sql = 'SELECT * FROM users WHERE id = ?';
        $parameters = [42];

        $exception = SqlException::fromPdoException($pdoException, $sql, $parameters);

        $message = $exception->getMessage();
        $this->assertStringContains($sql, $message);
        $this->assertStringContains('42', $message);
    }

    #[Test]
    public function preservesSqlStateFromPdoException(): void
    {
        $pdoException = new PDOException('SQLSTATE[23000]: Integrity constraint violation');
        $pdoException->errorInfo = ['23000', 1062, 'Duplicate entry'];

        $exception = SqlException::fromPdoException($pdoException);

        $this->assertSame('23000', $exception->getSqlState());
    }

    #[Test]
    public function getSqlStateReturnsNullWhenNotAvailable(): void
    {
        $exception = new SqlException('Error');

        $this->assertNull($exception->getSqlState());
    }
}
