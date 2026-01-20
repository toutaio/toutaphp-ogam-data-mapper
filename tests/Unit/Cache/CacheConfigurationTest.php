<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Touta\Ogam\Cache\CacheConfiguration;
use Touta\Ogam\Cache\EvictionPolicy;

#[CoversClass(CacheConfiguration::class)]
final class CacheConfigurationTest extends TestCase
{
    #[Test]
    public function canCreateWithDefaults(): void
    {
        $config = new CacheConfiguration('UserMapper');

        $this->assertSame('UserMapper', $config->namespace);
        $this->assertNull($config->implementation);
        $this->assertSame(EvictionPolicy::LRU, $config->eviction);
        $this->assertNull($config->flushInterval);
        $this->assertSame(1024, $config->size);
        $this->assertTrue($config->readOnly);
        $this->assertTrue($config->enabled);
    }

    #[Test]
    public function canCreateWithCustomValues(): void
    {
        $config = new CacheConfiguration(
            namespace: 'OrderMapper',
            implementation: 'Symfony\\Component\\Cache\\Adapter\\RedisAdapter',
            eviction: EvictionPolicy::FIFO,
            flushInterval: 60000,
            size: 512,
            readOnly: false,
            enabled: true,
        );

        $this->assertSame('OrderMapper', $config->namespace);
        $this->assertSame('Symfony\\Component\\Cache\\Adapter\\RedisAdapter', $config->implementation);
        $this->assertSame(EvictionPolicy::FIFO, $config->eviction);
        $this->assertSame(60000, $config->flushInterval);
        $this->assertSame(512, $config->size);
        $this->assertFalse($config->readOnly);
        $this->assertTrue($config->enabled);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(CacheConfiguration::class);

        $this->assertTrue($reflection->isReadOnly());
    }
}
