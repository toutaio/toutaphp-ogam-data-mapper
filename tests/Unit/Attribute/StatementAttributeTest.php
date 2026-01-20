<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Attribute;

use Attribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Touta\Ogam\Attribute\Delete;
use Touta\Ogam\Attribute\Insert;
use Touta\Ogam\Attribute\Mapper;
use Touta\Ogam\Attribute\Select;
use Touta\Ogam\Attribute\Update;
use Touta\Ogam\Mapping\FetchType;

#[CoversClass(Select::class)]
#[CoversClass(Insert::class)]
#[CoversClass(Update::class)]
#[CoversClass(Delete::class)]
final class StatementAttributeTest extends TestCase
{
    #[Test]
    public function selectCanBeInstantiated(): void
    {
        $select = new Select('SELECT * FROM users');

        $this->assertInstanceOf(Select::class, $select);
        $this->assertSame('SELECT * FROM users', $select->sql);
    }

    #[Test]
    public function selectHasDefaultValues(): void
    {
        $select = new Select('SELECT * FROM users');

        $this->assertNull($select->resultMap);
        $this->assertNull($select->resultType);
        $this->assertSame(0, $select->timeout);
        $this->assertSame(FetchType::EAGER, $select->fetchType);
    }

    #[Test]
    public function selectAcceptsAllOptions(): void
    {
        $select = new Select(
            sql: 'SELECT * FROM users WHERE id = :id',
            resultMap: 'fullUserResult',
            resultType: 'App\\Entity\\User',
            timeout: 30,
            fetchType: FetchType::LAZY,
        );

        $this->assertSame('SELECT * FROM users WHERE id = :id', $select->sql);
        $this->assertSame('fullUserResult', $select->resultMap);
        $this->assertSame('App\\Entity\\User', $select->resultType);
        $this->assertSame(30, $select->timeout);
        $this->assertSame(FetchType::LAZY, $select->fetchType);
    }

    #[Test]
    public function selectIsTargetedAtMethods(): void
    {
        $reflection = new ReflectionClass(Select::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_METHOD, $instance->flags);
    }

    #[Test]
    public function insertCanBeInstantiated(): void
    {
        $insert = new Insert('INSERT INTO users (name) VALUES (:name)');

        $this->assertInstanceOf(Insert::class, $insert);
        $this->assertSame('INSERT INTO users (name) VALUES (:name)', $insert->sql);
    }

    #[Test]
    public function insertHasDefaultValues(): void
    {
        $insert = new Insert('INSERT INTO users (name) VALUES (:name)');

        $this->assertSame(0, $insert->timeout);
        $this->assertFalse($insert->flushCache);
    }

    #[Test]
    public function insertAcceptsAllOptions(): void
    {
        $insert = new Insert(
            sql: 'INSERT INTO users (name) VALUES (:name)',
            timeout: 60,
            flushCache: true,
        );

        $this->assertSame('INSERT INTO users (name) VALUES (:name)', $insert->sql);
        $this->assertSame(60, $insert->timeout);
        $this->assertTrue($insert->flushCache);
    }

    #[Test]
    public function insertIsTargetedAtMethods(): void
    {
        $reflection = new ReflectionClass(Insert::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_METHOD, $instance->flags);
    }

    #[Test]
    public function updateCanBeInstantiated(): void
    {
        $update = new Update('UPDATE users SET name = :name WHERE id = :id');

        $this->assertInstanceOf(Update::class, $update);
        $this->assertSame('UPDATE users SET name = :name WHERE id = :id', $update->sql);
    }

    #[Test]
    public function updateHasDefaultValues(): void
    {
        $update = new Update('UPDATE users SET name = :name WHERE id = :id');

        $this->assertSame(0, $update->timeout);
        $this->assertFalse($update->flushCache);
    }

    #[Test]
    public function updateAcceptsAllOptions(): void
    {
        $update = new Update(
            sql: 'UPDATE users SET name = :name WHERE id = :id',
            timeout: 45,
            flushCache: true,
        );

        $this->assertSame('UPDATE users SET name = :name WHERE id = :id', $update->sql);
        $this->assertSame(45, $update->timeout);
        $this->assertTrue($update->flushCache);
    }

    #[Test]
    public function updateIsTargetedAtMethods(): void
    {
        $reflection = new ReflectionClass(Update::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_METHOD, $instance->flags);
    }

    #[Test]
    public function deleteCanBeInstantiated(): void
    {
        $delete = new Delete('DELETE FROM users WHERE id = :id');

        $this->assertInstanceOf(Delete::class, $delete);
        $this->assertSame('DELETE FROM users WHERE id = :id', $delete->sql);
    }

    #[Test]
    public function deleteHasDefaultValues(): void
    {
        $delete = new Delete('DELETE FROM users WHERE id = :id');

        $this->assertSame(0, $delete->timeout);
        $this->assertFalse($delete->flushCache);
    }

    #[Test]
    public function deleteAcceptsAllOptions(): void
    {
        $delete = new Delete(
            sql: 'DELETE FROM users WHERE id = :id',
            timeout: 15,
            flushCache: true,
        );

        $this->assertSame('DELETE FROM users WHERE id = :id', $delete->sql);
        $this->assertSame(15, $delete->timeout);
        $this->assertTrue($delete->flushCache);
    }

    #[Test]
    public function deleteIsTargetedAtMethods(): void
    {
        $reflection = new ReflectionClass(Delete::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_METHOD, $instance->flags);
    }

    #[Test]
    public function attributesCanBeReadFromMapperInterface(): void
    {
        $reflection = new ReflectionClass(TestStatementMapperInterface::class);

        // Test Select
        $findByIdMethod = $reflection->getMethod('findById');
        $selectAttrs = $findByIdMethod->getAttributes(Select::class);
        $this->assertCount(1, $selectAttrs);
        $selectInstance = $selectAttrs[0]->newInstance();
        $this->assertSame('SELECT * FROM users WHERE id = :id', $selectInstance->sql);

        // Test Insert
        $insertMethod = $reflection->getMethod('insert');
        $insertAttrs = $insertMethod->getAttributes(Insert::class);
        $this->assertCount(1, $insertAttrs);
        $insertInstance = $insertAttrs[0]->newInstance();
        $this->assertSame('INSERT INTO users (name, email) VALUES (:name, :email)', $insertInstance->sql);

        // Test Update
        $updateMethod = $reflection->getMethod('update');
        $updateAttrs = $updateMethod->getAttributes(Update::class);
        $this->assertCount(1, $updateAttrs);
        $updateInstance = $updateAttrs[0]->newInstance();
        $this->assertSame('UPDATE users SET name = :name WHERE id = :id', $updateInstance->sql);

        // Test Delete
        $deleteMethod = $reflection->getMethod('delete');
        $deleteAttrs = $deleteMethod->getAttributes(Delete::class);
        $this->assertCount(1, $deleteAttrs);
        $deleteInstance = $deleteAttrs[0]->newInstance();
        $this->assertSame('DELETE FROM users WHERE id = :id', $deleteInstance->sql);
    }
}

#[Mapper]
interface TestStatementMapperInterface
{
    #[Select('SELECT * FROM users WHERE id = :id')]
    public function findById(int $id): ?object;

    #[Insert('INSERT INTO users (name, email) VALUES (:name, :email)')]
    public function insert(object $user): int;

    #[Update('UPDATE users SET name = :name WHERE id = :id')]
    public function update(object $user): int;

    #[Delete('DELETE FROM users WHERE id = :id')]
    public function delete(int $id): int;
}
