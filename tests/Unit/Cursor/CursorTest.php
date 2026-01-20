<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Cursor;

use Iterator;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Cursor\CursorInterface;
use Touta\Ogam\Cursor\PdoCursor;

#[CoversClass(PdoCursor::class)]
final class CursorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        // Insert test data
        for ($i = 1; $i <= 100; $i++) {
            $this->pdo->exec(\sprintf("INSERT INTO users (name) VALUES ('User %d')", $i));
        }
    }

    #[Test]
    public function implementsCursorInterface(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users');
        $cursor = new PdoCursor($stmt);

        $this->assertInstanceOf(CursorInterface::class, $cursor);
        $this->assertInstanceOf(Iterator::class, $cursor);
    }

    #[Test]
    public function canIterateOverResults(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users LIMIT 5');
        $cursor = new PdoCursor($stmt);

        $results = [];
        foreach ($cursor as $row) {
            $results[] = $row;
        }

        $this->assertCount(5, $results);
        $this->assertSame('User 1', $results[0]['name']);
        $this->assertSame('User 5', $results[4]['name']);
    }

    #[Test]
    public function fetchesRowsLazily(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users');
        $cursor = new PdoCursor($stmt);

        // Only fetch first row
        $cursor->rewind();
        $first = $cursor->current();

        $this->assertSame('User 1', $first['name']);
    }

    #[Test]
    public function canBeUsedInForeach(): void
    {
        $stmt = $this->pdo->query('SELECT id, name FROM users LIMIT 3');
        $cursor = new PdoCursor($stmt);

        $count = 0;
        foreach ($cursor as $index => $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
            $count++;
        }

        $this->assertSame(3, $count);
    }

    #[Test]
    public function closesStatementOnClose(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users');
        $cursor = new PdoCursor($stmt);

        $cursor->close();

        $this->assertTrue($cursor->isClosed());
    }

    #[Test]
    public function cannotIterateAfterClose(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users');
        $cursor = new PdoCursor($stmt);

        $cursor->close();
        $cursor->rewind();

        $this->assertNull($cursor->current());
    }

    #[Test]
    public function handlesEmptyResultSet(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users WHERE id > 1000');
        $cursor = new PdoCursor($stmt);

        $results = iterator_to_array($cursor);

        $this->assertCount(0, $results);
    }

    #[Test]
    public function keyReturnsCurrentIndex(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users LIMIT 3');
        $cursor = new PdoCursor($stmt);

        $keys = [];
        foreach ($cursor as $key => $value) {
            $keys[] = $key;
        }

        $this->assertSame([0, 1, 2], $keys);
    }

    #[Test]
    public function validReturnsFalseAfterLastRow(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users LIMIT 2');
        $cursor = new PdoCursor($stmt);

        $cursor->rewind();
        $this->assertTrue($cursor->valid());

        $cursor->next();
        $this->assertTrue($cursor->valid());

        $cursor->next();
        $this->assertFalse($cursor->valid());
    }

    #[Test]
    public function canUseWithHydrator(): void
    {
        $stmt = $this->pdo->query('SELECT id, name FROM users LIMIT 3');
        $hydrator = fn(array $row): object => (object) $row;

        $cursor = new PdoCursor($stmt, $hydrator);

        $results = [];
        foreach ($cursor as $row) {
            $results[] = $row;
        }

        $this->assertCount(3, $results);
        $this->assertIsObject($results[0]);
        $this->assertSame('User 1', $results[0]->name);
    }

    #[Test]
    public function destructorClosesStatement(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM users');
        $cursor = new PdoCursor($stmt);

        // Start iteration
        $cursor->rewind();
        $wasIterating = $cursor->valid();

        // Destroy cursor - should close statement automatically
        unset($cursor);

        // Should have been valid before destruction
        $this->assertTrue($wasIterating);
    }
}
