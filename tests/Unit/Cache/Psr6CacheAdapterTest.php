<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Touta\Ogam\Cache\CacheKey;
use Touta\Ogam\Cache\Psr6CacheAdapter;
use Touta\Ogam\Contract\CacheInterface;

#[CoversClass(Psr6CacheAdapter::class)]
final class Psr6CacheAdapterTest extends TestCase
{
    private CacheItemPoolInterface $pool;

    private Psr6CacheAdapter $adapter;

    protected function setUp(): void
    {
        $this->pool = $this->createMock(CacheItemPoolInterface::class);
        $this->adapter = new Psr6CacheAdapter($this->pool);
    }

    #[Test]
    public function implementsCacheInterface(): void
    {
        $this->assertInstanceOf(CacheInterface::class, $this->adapter);
    }

    #[Test]
    public function getReturnsNullOnCacheMiss(): void
    {
        $key = new CacheKey('User.findById', ['id' => 1]);

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $this->pool->method('getItem')->willReturn($item);

        $result = $this->adapter->get($key);

        $this->assertNull($result);
    }

    #[Test]
    public function getReturnsCachedValueOnHit(): void
    {
        $key = new CacheKey('User.findById', ['id' => 1]);
        $cachedValue = ['id' => 1, 'name' => 'John'];

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($cachedValue);

        $this->pool->method('getItem')->willReturn($item);

        $result = $this->adapter->get($key);

        $this->assertSame($cachedValue, $result);
    }

    #[Test]
    public function putStoresValueInCache(): void
    {
        $key = new CacheKey('User.findById', ['id' => 1]);
        $value = ['id' => 1, 'name' => 'John'];

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('set')
            ->with($value)
            ->willReturnSelf();

        $this->pool->method('getItem')->willReturn($item);
        $this->pool->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $this->adapter->put($key, $value);
    }

    #[Test]
    public function putWithTtlSetsExpiration(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $adapter = new Psr6CacheAdapter($pool, 3600);

        $key = new CacheKey('User.findById', ['id' => 1]);
        $value = ['id' => 1, 'name' => 'John'];

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())
            ->method('set')
            ->with($value)
            ->willReturnSelf();
        $item->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();

        $pool->method('getItem')->willReturn($item);
        $pool->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $adapter->put($key, $value);
    }

    #[Test]
    public function hasReturnsTrueWhenItemExists(): void
    {
        $key = new CacheKey('User.findById', ['id' => 1]);
        $normalizedKey = $this->normalizeKey($key->toString());

        $this->pool->method('hasItem')
            ->with($normalizedKey)
            ->willReturn(true);

        $this->assertTrue($this->adapter->has($key));
    }

    #[Test]
    public function hasReturnsFalseWhenItemDoesNotExist(): void
    {
        $key = new CacheKey('User.findById', ['id' => 1]);
        $normalizedKey = $this->normalizeKey($key->toString());

        $this->pool->method('hasItem')
            ->with($normalizedKey)
            ->willReturn(false);

        $this->assertFalse($this->adapter->has($key));
    }

    #[Test]
    public function removeDeletesItem(): void
    {
        $key = new CacheKey('User.findById', ['id' => 1]);
        $normalizedKey = $this->normalizeKey($key->toString());

        $this->pool->expects($this->once())
            ->method('deleteItem')
            ->with($normalizedKey)
            ->willReturn(true);

        $this->adapter->remove($key);
    }

    #[Test]
    public function clearDeletesAllItems(): void
    {
        $this->pool->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $this->adapter->clear();
    }

    #[Test]
    public function countReturnsZeroAsPsr6DoesNotSupportCounting(): void
    {
        // PSR-6 does not support counting cached items
        $this->assertSame(0, $this->adapter->count());
    }

    #[Test]
    public function usesNamespacePrefixForKeys(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $adapter = new Psr6CacheAdapter($pool, null, 'myapp_');

        $key = new CacheKey('User.findById', ['id' => 1]);
        $expectedKey = $this->normalizeKey('myapp_' . $key->toString());

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $pool->expects($this->once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($item);

        $adapter->get($key);
    }

    #[Test]
    public function sanitizesInvalidCacheKeyCharacters(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $adapter = new Psr6CacheAdapter($pool);

        // CacheKey may contain characters invalid for PSR-6
        $key = new CacheKey('User.findById', ['id' => 1]);

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        // The key should be sanitized to remove invalid characters
        $pool->expects($this->once())
            ->method('getItem')
            ->with($this->callback(function (string $actualKey): bool {
                // PSR-6 reserved characters: {}()/\@:
                return preg_match('/[{}()\/\\\\@:]/', $actualKey) === 0;
            }))
            ->willReturn($item);

        $adapter->get($key);
    }

    private function normalizeKey(string $key): string
    {
        return preg_replace('/[{}()\/\\\\@:]/', '_', $key) ?? $key;
    }
}
