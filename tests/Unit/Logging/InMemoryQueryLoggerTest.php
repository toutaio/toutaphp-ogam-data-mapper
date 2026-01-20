<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Logging\InMemoryQueryLogger;
use Touta\Ogam\Logging\QueryLogEntry;
use Touta\Ogam\Logging\QueryLoggerInterface;

#[CoversClass(InMemoryQueryLogger::class)]
final class InMemoryQueryLoggerTest extends TestCase
{
    #[Test]
    public function implementsInterface(): void
    {
        $logger = new InMemoryQueryLogger();

        $this->assertInstanceOf(QueryLoggerInterface::class, $logger);
    }

    #[Test]
    public function startsWithEmptyEntries(): void
    {
        $logger = new InMemoryQueryLogger();

        $this->assertEmpty($logger->getEntries());
    }

    #[Test]
    public function logsEntry(): void
    {
        $logger = new InMemoryQueryLogger();
        $entry = new QueryLogEntry(
            sql: 'SELECT * FROM users',
            parameters: [],
            executionTimeMs: 5.0,
        );

        $logger->log($entry);

        $entries = $logger->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($entry, $entries[0]);
    }

    #[Test]
    public function logsMultipleEntries(): void
    {
        $logger = new InMemoryQueryLogger();

        $entry1 = new QueryLogEntry('SELECT 1', [], 1.0);
        $entry2 = new QueryLogEntry('SELECT 2', [], 2.0);
        $entry3 = new QueryLogEntry('SELECT 3', [], 3.0);

        $logger->log($entry1);
        $logger->log($entry2);
        $logger->log($entry3);

        $entries = $logger->getEntries();
        $this->assertCount(3, $entries);
        $this->assertSame($entry1, $entries[0]);
        $this->assertSame($entry2, $entries[1]);
        $this->assertSame($entry3, $entries[2]);
    }

    #[Test]
    public function clearsEntries(): void
    {
        $logger = new InMemoryQueryLogger();
        $logger->log(new QueryLogEntry('SELECT 1', [], 1.0));
        $logger->log(new QueryLogEntry('SELECT 2', [], 2.0));

        $logger->clear();

        $this->assertEmpty($logger->getEntries());
    }

    #[Test]
    public function getTotalExecutionTime(): void
    {
        $logger = new InMemoryQueryLogger();
        $logger->log(new QueryLogEntry('SELECT 1', [], 10.0));
        $logger->log(new QueryLogEntry('SELECT 2', [], 20.0));
        $logger->log(new QueryLogEntry('SELECT 3', [], 30.0));

        $this->assertSame(60.0, $logger->getTotalExecutionTime());
    }

    #[Test]
    public function getQueryCount(): void
    {
        $logger = new InMemoryQueryLogger();
        $logger->log(new QueryLogEntry('SELECT 1', [], 1.0));
        $logger->log(new QueryLogEntry('SELECT 2', [], 2.0));

        $this->assertSame(2, $logger->getQueryCount());
    }

    #[Test]
    public function getLastEntry(): void
    {
        $logger = new InMemoryQueryLogger();
        $entry1 = new QueryLogEntry('SELECT 1', [], 1.0);
        $entry2 = new QueryLogEntry('SELECT 2', [], 2.0);

        $logger->log($entry1);
        $logger->log($entry2);

        $this->assertSame($entry2, $logger->getLastEntry());
    }

    #[Test]
    public function getLastEntryReturnsNullWhenEmpty(): void
    {
        $logger = new InMemoryQueryLogger();

        $this->assertNull($logger->getLastEntry());
    }
}
