<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Touta\Ogam\Logging\Psr3QueryLogger;
use Touta\Ogam\Logging\QueryLogEntry;
use Touta\Ogam\Logging\QueryLoggerInterface;

#[CoversClass(Psr3QueryLogger::class)]
final class Psr3QueryLoggerTest extends TestCase
{
    #[Test]
    public function implementsInterface(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $logger = new Psr3QueryLogger($psrLogger);

        $this->assertInstanceOf(QueryLoggerInterface::class, $logger);
    }

    #[Test]
    public function logsQueryToDebugByDefault(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                $this->stringContains('SELECT * FROM users'),
                $this->callback(function (array $context) {
                    return $context['sql'] === 'SELECT * FROM users'
                        && $context['parameters'] === [1]
                        && $context['execution_time_ms'] === 12.5;
                }),
            );

        $logger = new Psr3QueryLogger($psrLogger);
        $entry = new QueryLogEntry(
            sql: 'SELECT * FROM users',
            parameters: [1],
            executionTimeMs: 12.5,
        );

        $logger->log($entry);
    }

    #[Test]
    public function logsWithCustomLogLevel(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                $this->anything(),
                $this->anything(),
            );

        $logger = new Psr3QueryLogger($psrLogger, LogLevel::INFO);
        $entry = new QueryLogEntry(
            sql: 'SELECT 1',
            parameters: [],
            executionTimeMs: 1.0,
        );

        $logger->log($entry);
    }

    #[Test]
    public function includesRowCountInContext(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger->expects($this->once())
            ->method('log')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $context) {
                    return $context['row_count'] === 5;
                }),
            );

        $logger = new Psr3QueryLogger($psrLogger);
        $entry = new QueryLogEntry(
            sql: 'SELECT * FROM users',
            parameters: [],
            executionTimeMs: 10.0,
            rowCount: 5,
        );

        $logger->log($entry);
    }

    #[Test]
    public function includesStatementIdInContext(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger->expects($this->once())
            ->method('log')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $context) {
                    return $context['statement_id'] === 'UserMapper.findAll';
                }),
            );

        $logger = new Psr3QueryLogger($psrLogger);
        $entry = new QueryLogEntry(
            sql: 'SELECT * FROM users',
            parameters: [],
            executionTimeMs: 10.0,
            statementId: 'UserMapper.findAll',
        );

        $logger->log($entry);
    }

    #[Test]
    public function getEntriesReturnsEmpty(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $logger = new Psr3QueryLogger($psrLogger);

        // PSR-3 logger doesn't store entries
        $this->assertEmpty($logger->getEntries());
    }

    #[Test]
    public function clearIsNoOp(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $logger = new Psr3QueryLogger($psrLogger);

        // Should not throw
        $logger->clear();
        $this->assertEmpty($logger->getEntries());
    }

    #[Test]
    public function formatsMessageCorrectly(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger->expects($this->once())
            ->method('log')
            ->with(
                $this->anything(),
                $this->stringContains('[12.50ms]'),
                $this->anything(),
            );

        $logger = new Psr3QueryLogger($psrLogger);
        $entry = new QueryLogEntry(
            sql: 'SELECT * FROM users',
            parameters: [],
            executionTimeMs: 12.5,
        );

        $logger->log($entry);
    }
}
