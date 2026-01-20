<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Touta\Ogam\Logging\QueryLogEntry;

#[CoversClass(QueryLogEntry::class)]
final class QueryLogEntryTest extends TestCase
{
    #[Test]
    public function queryLogEntryCanBeCreated(): void
    {
        $entry = new QueryLogEntry(
            sql: 'SELECT * FROM users WHERE id = ?',
            parameters: [1],
            executionTimeMs: 12.5,
            rowCount: 1,
            statementId: 'UserMapper.findById',
        );

        $this->assertSame('SELECT * FROM users WHERE id = ?', $entry->sql);
        $this->assertSame([1], $entry->parameters);
        $this->assertSame(12.5, $entry->executionTimeMs);
        $this->assertSame(1, $entry->rowCount);
        $this->assertSame('UserMapper.findById', $entry->statementId);
    }

    #[Test]
    public function queryLogEntryHasOptionalFields(): void
    {
        $entry = new QueryLogEntry(
            sql: 'SELECT * FROM users',
            parameters: [],
            executionTimeMs: 5.0,
        );

        $this->assertNull($entry->rowCount);
        $this->assertNull($entry->statementId);
    }

    #[Test]
    public function queryLogEntryIsReadonly(): void
    {
        $reflection = new ReflectionClass(QueryLogEntry::class);

        $this->assertTrue($reflection->isReadOnly());
    }
}
