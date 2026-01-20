<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\SessionFactoryInterface;
use Touta\Ogam\SessionFactoryBuilder;
use Touta\Ogam\Session\DefaultSessionFactory;

final class SessionFactoryBuilderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ogam_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testWithConfigurationObjectReturnsBuilder(): void
    {
        $configuration = new Configuration();
        $builder = new SessionFactoryBuilder();

        $result = $builder->withConfigurationObject($configuration);

        $this->assertInstanceOf(SessionFactoryBuilder::class, $result);
        $this->assertSame($builder, $result);
    }

    public function testWithConfigurationReturnsBuilder(): void
    {
        $builder = new SessionFactoryBuilder();

        $result = $builder->withConfiguration('/path/to/config.xml');

        $this->assertInstanceOf(SessionFactoryBuilder::class, $result);
        $this->assertSame($builder, $result);
    }

    public function testWithXmlConfigurationReturnsBuilder(): void
    {
        $builder = new SessionFactoryBuilder();

        $result = $builder->withXmlConfiguration('<configuration></configuration>');

        $this->assertInstanceOf(SessionFactoryBuilder::class, $result);
        $this->assertSame($builder, $result);
    }

    public function testBuildWithConfigurationObjectCreatesSessionFactory(): void
    {
        $configuration = new Configuration();
        $builder = new SessionFactoryBuilder();

        $sessionFactory = $builder
            ->withConfigurationObject($configuration)
            ->build();

        $this->assertInstanceOf(SessionFactoryInterface::class, $sessionFactory);
        $this->assertInstanceOf(DefaultSessionFactory::class, $sessionFactory);
    }

    public function testBuildWithXmlConfigurationCreatesSessionFactory(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments default="development">
        <environment id="development">
            <dataSource type="UNPOOLED">
                <property name="driver" value="pdo_sqlite"/>
                <property name="url" value="sqlite::memory:"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $builder = new SessionFactoryBuilder();

        $sessionFactory = $builder
            ->withXmlConfiguration($xml)
            ->build();

        $this->assertInstanceOf(SessionFactoryInterface::class, $sessionFactory);
    }

    public function testBuildWithConfigurationFileCreatesSessionFactory(): void
    {
        $configPath = $this->tempDir . '/config.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments default="development">
        <environment id="development">
            <dataSource type="UNPOOLED">
                <property name="driver" value="pdo_sqlite"/>
                <property name="url" value="sqlite::memory:"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;
        file_put_contents($configPath, $xml);

        $builder = new SessionFactoryBuilder();

        $sessionFactory = $builder
            ->withConfiguration($configPath)
            ->build();

        $this->assertInstanceOf(SessionFactoryInterface::class, $sessionFactory);
    }

    public function testBuildWithoutConfigurationThrowsException(): void
    {
        $builder = new SessionFactoryBuilder();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No configuration provided');

        $builder->build();
    }

    public function testBuilderUsesConfigurationObjectFirst(): void
    {
        $configuration = new Configuration();
        $builder = new SessionFactoryBuilder();

        $sessionFactory = $builder
            ->withConfiguration('/nonexistent/path.xml')
            ->withXmlConfiguration('<invalid>xml</invalid>')
            ->withConfigurationObject($configuration)
            ->build();

        $this->assertInstanceOf(SessionFactoryInterface::class, $sessionFactory);
    }

    public function testBuilderCanBuildMultipleTimes(): void
    {
        $configuration = new Configuration();
        $builder = new SessionFactoryBuilder();
        $builder->withConfigurationObject($configuration);

        $factory1 = $builder->build();
        $factory2 = $builder->build();

        $this->assertInstanceOf(SessionFactoryInterface::class, $factory1);
        $this->assertInstanceOf(SessionFactoryInterface::class, $factory2);
        $this->assertNotSame($factory1, $factory2);
    }
}
