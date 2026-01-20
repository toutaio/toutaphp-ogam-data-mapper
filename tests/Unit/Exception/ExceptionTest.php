<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use Touta\Ogam\Exception\BindingException;
use Touta\Ogam\Exception\ConfigurationException;
use Touta\Ogam\Exception\ExecutorException;
use Touta\Ogam\Exception\OgamException;
use Touta\Ogam\Exception\ParsingException;
use Touta\Ogam\Exception\TypeException;

final class ExceptionTest extends TestCase
{
    public function testOgamExceptionExtendsRuntimeException(): void
    {
        $exception = new OgamException('Test message');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testBindingExceptionStatementNotFound(): void
    {
        $exception = BindingException::statementNotFound('UserMapper.findById');

        $this->assertInstanceOf(BindingException::class, $exception);
        $this->assertInstanceOf(OgamException::class, $exception);
        $this->assertStringContainsString('UserMapper.findById', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    public function testBindingExceptionResultMapNotFound(): void
    {
        $exception = BindingException::resultMapNotFound('userResultMap');

        $this->assertInstanceOf(BindingException::class, $exception);
        $this->assertStringContainsString('userResultMap', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    public function testBindingExceptionParameterMissing(): void
    {
        $exception = BindingException::parameterMissing('userId');

        $this->assertInstanceOf(BindingException::class, $exception);
        $this->assertStringContainsString('userId', $exception->getMessage());
        $this->assertStringContainsString('required', $exception->getMessage());
    }

    public function testBindingExceptionInvalidMapKey(): void
    {
        $exception = BindingException::invalidMapKey('id');

        $this->assertInstanceOf(BindingException::class, $exception);
        $this->assertStringContainsString('id', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    public function testConfigurationExceptionMissingEnvironment(): void
    {
        $exception = ConfigurationException::missingEnvironment('production');

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertInstanceOf(OgamException::class, $exception);
        $this->assertStringContainsString('production', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    public function testConfigurationExceptionMissingMapper(): void
    {
        $exception = ConfigurationException::missingMapper('UserMapper');

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertStringContainsString('UserMapper', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
    }

    public function testConfigurationExceptionInvalidConfiguration(): void
    {
        $exception = ConfigurationException::invalidConfiguration('DataSource must be configured');

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertStringContainsString('Invalid configuration', $exception->getMessage());
        $this->assertStringContainsString('DataSource must be configured', $exception->getMessage());
    }

    public function testExecutorExceptionQueryFailed(): void
    {
        $exception = ExecutorException::queryFailed(
            'SELECT * FROM users',
            'Connection lost',
        );

        $this->assertInstanceOf(ExecutorException::class, $exception);
        $this->assertInstanceOf(OgamException::class, $exception);
        $this->assertStringContainsString('SELECT * FROM users', $exception->getMessage());
        $this->assertStringContainsString('Connection lost', $exception->getMessage());
    }

    public function testExecutorExceptionUpdateFailed(): void
    {
        $exception = ExecutorException::updateFailed(
            'UPDATE users SET name = ?',
            'Constraint violation',
        );

        $this->assertInstanceOf(ExecutorException::class, $exception);
        $this->assertStringContainsString('UPDATE users SET name = ?', $exception->getMessage());
        $this->assertStringContainsString('Constraint violation', $exception->getMessage());
    }

    public function testExecutorExceptionTransactionFailed(): void
    {
        $exception = ExecutorException::transactionFailed('Deadlock detected');

        $this->assertInstanceOf(ExecutorException::class, $exception);
        $this->assertStringContainsString('Transaction failed', $exception->getMessage());
        $this->assertStringContainsString('Deadlock detected', $exception->getMessage());
    }

    public function testExecutorExceptionSessionClosed(): void
    {
        $exception = ExecutorException::sessionClosed();

        $this->assertInstanceOf(ExecutorException::class, $exception);
        $this->assertStringContainsString('session is closed', $exception->getMessage());
    }

    public function testParsingExceptionInvalidXml(): void
    {
        $exception = ParsingException::invalidXml(
            '/path/to/mapper.xml',
            'Unexpected tag',
        );

        $this->assertInstanceOf(ParsingException::class, $exception);
        $this->assertInstanceOf(OgamException::class, $exception);
        $this->assertStringContainsString('/path/to/mapper.xml', $exception->getMessage());
        $this->assertStringContainsString('Unexpected tag', $exception->getMessage());
    }

    public function testParsingExceptionMissingAttribute(): void
    {
        $exception = ParsingException::missingAttribute('select', 'id');

        $this->assertInstanceOf(ParsingException::class, $exception);
        $this->assertStringContainsString('<select>', $exception->getMessage());
        $this->assertStringContainsString('"id"', $exception->getMessage());
    }

    public function testParsingExceptionInvalidExpression(): void
    {
        $exception = ParsingException::invalidExpression(
            '${invalid}',
            'Unknown property',
        );

        $this->assertInstanceOf(ParsingException::class, $exception);
        $this->assertStringContainsString('${invalid}', $exception->getMessage());
        $this->assertStringContainsString('Unknown property', $exception->getMessage());
    }

    public function testParsingExceptionFileNotFound(): void
    {
        $exception = ParsingException::fileNotFound('/path/to/missing.xml');

        $this->assertInstanceOf(ParsingException::class, $exception);
        $this->assertStringContainsString('/path/to/missing.xml', $exception->getMessage());
    }

    public function testTypeExceptionUnsupportedType(): void
    {
        $exception = TypeException::unsupportedType('CustomType');

        $this->assertInstanceOf(TypeException::class, $exception);
        $this->assertInstanceOf(OgamException::class, $exception);
        $this->assertStringContainsString('CustomType', $exception->getMessage());
        $this->assertStringContainsString('Unsupported type', $exception->getMessage());
    }

    public function testTypeExceptionConversionFailed(): void
    {
        $exception = TypeException::conversionFailed('string', 'int', 'Not numeric');

        $this->assertInstanceOf(TypeException::class, $exception);
        $this->assertStringContainsString('string', $exception->getMessage());
        $this->assertStringContainsString('int', $exception->getMessage());
        $this->assertStringContainsString('Not numeric', $exception->getMessage());
    }

    public function testTypeExceptionInvalidEnumValueWithScalar(): void
    {
        $exception = TypeException::invalidEnumValue('Status', 'unknown');

        $this->assertInstanceOf(TypeException::class, $exception);
        $this->assertStringContainsString('unknown', $exception->getMessage());
        $this->assertStringContainsString('Status', $exception->getMessage());
    }

    public function testTypeExceptionInvalidEnumValueWithNonScalar(): void
    {
        $exception = TypeException::invalidEnumValue('Status', ['array']);

        $this->assertInstanceOf(TypeException::class, $exception);
        $this->assertStringContainsString('array', $exception->getMessage());
        $this->assertStringContainsString('Status', $exception->getMessage());
    }

    public function testTypeExceptionInvalidEnumValueWithStringable(): void
    {
        $stringable = new class() implements Stringable {
            public function __toString(): string
            {
                return 'stringable_value';
            }
        };

        $exception = TypeException::invalidEnumValue('Status', $stringable);

        $this->assertInstanceOf(TypeException::class, $exception);
        $this->assertStringContainsString('stringable_value', $exception->getMessage());
    }
}
