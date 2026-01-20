<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Logging\InMemoryQueryLogger;

#[CoversClass(Configuration::class)]
final class ConfigurationQueryLoggerTest extends TestCase
{
    #[Test]
    public function queryLoggerIsNullByDefault(): void
    {
        $configuration = new Configuration();

        $this->assertNull($configuration->getQueryLogger());
    }

    #[Test]
    public function canSetQueryLogger(): void
    {
        $configuration = new Configuration();
        $logger = new InMemoryQueryLogger();

        $configuration->setQueryLogger($logger);

        $this->assertSame($logger, $configuration->getQueryLogger());
    }

    #[Test]
    public function setQueryLoggerReturnsSelf(): void
    {
        $configuration = new Configuration();
        $logger = new InMemoryQueryLogger();

        $result = $configuration->setQueryLogger($logger);

        $this->assertSame($configuration, $result);
    }

    #[Test]
    public function queryLoggerCanBeSetToNull(): void
    {
        $configuration = new Configuration();
        $logger = new InMemoryQueryLogger();

        $configuration->setQueryLogger($logger);
        $configuration->setQueryLogger(null);

        $this->assertNull($configuration->getQueryLogger());
    }
}
