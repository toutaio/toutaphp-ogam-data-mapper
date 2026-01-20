<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Parsing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Executor\ExecutorType;
use Touta\Ogam\Parsing\XmlConfigurationParser;

final class XmlConfigurationParserTest extends TestCase
{
    private XmlConfigurationParser $parser;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->parser = new XmlConfigurationParser();
        $this->tempDir = sys_get_temp_dir() . '/ogam_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testParseMinimalConfiguration(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testParseFailsWhenFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found:');

        $this->parser->parse('/nonexistent/path/config.xml');
    }

    public function testParseFailsWithInvalidXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse configuration XML');

        $this->parser->parseXml('this is not valid xml');
    }

    public function testParseFailsWithWrongRootElement(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<wrongElement>
</wrongElement>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid configuration: root element must be <configuration>');

        $this->parser->parseXml($xml);
    }

    public function testParseFailsWithEmptyXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse configuration XML');

        $this->parser->parseXml('');
    }

    public function testParseSettingsWithAllOptions(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="cacheEnabled" value="false"/>
        <setting name="lazyLoadingEnabled" value="true"/>
        <setting name="mapUnderscoreToCamelCase" value="true"/>
        <setting name="defaultExecutorType" value="BATCH"/>
        <setting name="defaultStatementTimeout" value="30000"/>
        <setting name="useGeneratedKeys" value="true"/>
        <setting name="debugMode" value="true"/>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertFalse($config->isCacheEnabled());
        $this->assertTrue($config->isLazyLoadingEnabled());
        $this->assertTrue($config->isMapUnderscoreToCamelCase());
        $this->assertSame(ExecutorType::BATCH, $config->getDefaultExecutorType());
        $this->assertSame(30000, $config->getDefaultStatementTimeout());
        $this->assertTrue($config->isUseGeneratedKeys());
        $this->assertTrue($config->isDebugMode());
    }

    public function testParseSettingsWithBooleanVariations(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="cacheEnabled" value="1"/>
        <setting name="lazyLoadingEnabled" value="yes"/>
        <setting name="mapUnderscoreToCamelCase" value="on"/>
        <setting name="useGeneratedKeys" value="TRUE"/>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertTrue($config->isCacheEnabled());
        $this->assertTrue($config->isLazyLoadingEnabled());
        $this->assertTrue($config->isMapUnderscoreToCamelCase());
        $this->assertTrue($config->isUseGeneratedKeys());
    }

    public function testParseSettingsWithBooleanFalseVariations(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="cacheEnabled" value="false"/>
        <setting name="lazyLoadingEnabled" value="0"/>
        <setting name="mapUnderscoreToCamelCase" value="no"/>
        <setting name="useGeneratedKeys" value="off"/>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertFalse($config->isCacheEnabled());
        $this->assertFalse($config->isLazyLoadingEnabled());
        $this->assertFalse($config->isMapUnderscoreToCamelCase());
        $this->assertFalse($config->isUseGeneratedKeys());
    }

    public function testParseSettingsWithExecutorTypes(): void
    {
        $xmlReuse = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="defaultExecutorType" value="REUSE"/>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xmlReuse);
        $this->assertSame(ExecutorType::REUSE, $config->getDefaultExecutorType());

        $xmlBatch = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="defaultExecutorType" value="BATCH"/>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xmlBatch);
        $this->assertSame(ExecutorType::BATCH, $config->getDefaultExecutorType());

        $xmlSimple = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="defaultExecutorType" value="SIMPLE"/>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xmlSimple);
        $this->assertSame(ExecutorType::SIMPLE, $config->getDefaultExecutorType());
    }

    public function testParseSettingsWithUnknownExecutorTypeDefaultsToSimple(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="defaultExecutorType" value="UNKNOWN"/>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertSame(ExecutorType::SIMPLE, $config->getDefaultExecutorType());
    }

    public function testParseSettingsIgnoresUnknownSettings(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="unknownSetting" value="someValue"/>
        <setting name="cacheEnabled" value="false"/>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertFalse($config->isCacheEnabled());
    }

    public function testParseTypeAliases(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <typeAliases>
        <typeAlias alias="User" type="App\Entity\User"/>
        <typeAlias alias="Post" type="App\Entity\Post"/>
    </typeAliases>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertSame('App\Entity\User', $config->resolveTypeAlias('User'));
        $this->assertSame('App\Entity\Post', $config->resolveTypeAlias('Post'));
    }

    public function testParseTypeAliasesAreCaseInsensitive(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <typeAliases>
        <typeAlias alias="User" type="App\Entity\User"/>
    </typeAliases>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertSame('App\Entity\User', $config->resolveTypeAlias('user'));
        $this->assertSame('App\Entity\User', $config->resolveTypeAlias('USER'));
        $this->assertSame('App\Entity\User', $config->resolveTypeAlias('User'));
    }

    public function testParseEnvironmentsWithDefaultAttribute(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments default="production">
        <environment id="production">
            <transactionManager type="JDBC"/>
            <dataSource type="POOLED">
                <property name="driver" value="mysql"/>
                <property name="host" value="localhost"/>
                <property name="port" value="3306"/>
                <property name="database" value="mydb"/>
                <property name="username" value="root"/>
                <property name="password" value="secret"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertSame('production', $config->getDefaultEnvironment());
        $this->assertNotNull($config->getEnvironment('production'));
    }

    public function testParseEnvironmentWithManagedTransactionManager(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="MANAGED"/>
            <dataSource type="SIMPLE">
                <property name="dsn" value="sqlite::memory:"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $env = $config->getEnvironment('test');
        $this->assertNotNull($env);
        $this->assertSame('test', $env->getId());
    }

    public function testParseEnvironmentWithJdbcTransactionManager(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="dsn" value="sqlite::memory:"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $env = $config->getEnvironment('test');
        $this->assertNotNull($env);
    }

    public function testParseEnvironmentFailsWithoutDataSource(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
        </environment>
    </environments>
</configuration>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Environment must have a dataSource');

        $this->parser->parseXml($xml);
    }

    public function testParseDataSourceWithDsn(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="dsn" value="mysql:host=localhost;dbname=test"/>
                <property name="username" value="user"/>
                <property name="password" value="pass"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertNotNull($config->getEnvironment('test'));
    }

    public function testParseDataSourceBuildsMySQL_Dsn(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="UNPOOLED">
                <property name="driver" value="mysql"/>
                <property name="host" value="localhost"/>
                <property name="port" value="3306"/>
                <property name="database" value="testdb"/>
                <property name="username" value="user"/>
                <property name="password" value="pass"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertNotNull($config->getEnvironment('test'));
    }

    public function testParseDataSourceBuildsPostgreSQLDsn(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="POOLED">
                <property name="driver" value="pgsql"/>
                <property name="host" value="localhost"/>
                <property name="port" value="5432"/>
                <property name="database" value="testdb"/>
                <property name="username" value="user"/>
                <property name="password" value="pass"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertNotNull($config->getEnvironment('test'));
    }

    public function testParseDataSourceBuildsSQLiteDsn(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="driver" value="sqlite"/>
                <property name="database" value="/tmp/test.db"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertNotNull($config->getEnvironment('test'));
    }

    public function testParseDataSourceWithUnknownDriverThrowsException(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="driver" value="unknown"/>
                <property name="database" value="testdb"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown driver: unknown');

        $this->parser->parseXml($xml);
    }

    public function testParseDataSourceWithDbNameProperty(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="driver" value="mysql"/>
                <property name="host" value="localhost"/>
                <property name="dbname" value="testdb"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertNotNull($config->getEnvironment('test'));
    }

    public function testParseDataSourceWithEnvironmentVariableInterpolation(): void
    {
        putenv('TEST_DB_HOST=testhost');
        putenv('TEST_DB_USER=testuser');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="driver" value="mysql"/>
                <property name="host" value="\${TEST_DB_HOST}"/>
                <property name="database" value="testdb"/>
                <property name="username" value="\${TEST_DB_USER}"/>
                <property name="password" value="pass"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertNotNull($config->getEnvironment('test'));

        putenv('TEST_DB_HOST');
        putenv('TEST_DB_USER');
    }

    public function testParseDataSourceWithNonExistentEnvironmentVariable(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments>
        <environment id="test">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="driver" value="mysql"/>
                <property name="host" value="\${NONEXISTENT_VAR}"/>
                <property name="database" value="testdb"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);
        $this->assertNotNull($config->getEnvironment('test'));
    }

    public function testParseMultipleEnvironments(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <environments default="development">
        <environment id="development">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="dsn" value="sqlite::memory:"/>
            </dataSource>
        </environment>
        <environment id="production">
            <transactionManager type="JDBC"/>
            <dataSource type="POOLED">
                <property name="dsn" value="mysql:host=prod;dbname=app"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertNotNull($config->getEnvironment('development'));
        $this->assertNotNull($config->getEnvironment('production'));
        $this->assertSame('development', $config->getDefaultEnvironment());
    }

    public function testParseMappers(): void
    {
        $mapperXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<mapper namespace="TestMapper">
    <select id="findAll">
        SELECT * FROM users
    </select>
</mapper>
XML;

        $mapperPath = $this->tempDir . '/TestMapper.xml';
        file_put_contents($mapperPath, $mapperXml);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <mappers>
        <mapper resource="TestMapper.xml"/>
    </mappers>
</configuration>
XML;

        $config = $this->parser->parseXml($xml, $this->tempDir);

        $this->assertTrue($config->hasMappedStatement('TestMapper.findAll'));
    }

    public function testParseMappersWithAbsolutePath(): void
    {
        $mapperXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<mapper namespace="TestMapper">
    <select id="findAll">
        SELECT * FROM users
    </select>
</mapper>
XML;

        $mapperPath = $this->tempDir . '/TestMapper.xml';
        file_put_contents($mapperPath, $mapperXml);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <mappers>
        <mapper resource="{$mapperPath}"/>
    </mappers>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertTrue($config->hasMappedStatement('TestMapper.findAll'));
    }

    public function testParseMappersWithClass(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <mappers>
        <mapper class="Touta\Ogam\Configuration"/>
    </mappers>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertTrue($config->hasMapper('Touta\Ogam\Configuration'));
    }

    public function testParseMappersWithNonExistentClass(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <mappers>
        <mapper class="NonExistent\Class"/>
    </mappers>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertFalse($config->hasMapper('NonExistent\Class'));
    }

    public function testParseFromFile(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="cacheEnabled" value="false"/>
    </settings>
</configuration>
XML;

        $configPath = $this->tempDir . '/config.xml';
        file_put_contents($configPath, $xml);

        $config = $this->parser->parse($configPath);

        $this->assertFalse($config->isCacheEnabled());
    }

    public function testParseFromFileWithMalformedXml(): void
    {
        $configPath = $this->tempDir . '/config.xml';
        file_put_contents($configPath, 'not valid xml');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse configuration file:');

        $this->parser->parse($configPath);
    }

    public function testParseCompleteConfiguration(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
        <setting name="cacheEnabled" value="true"/>
        <setting name="lazyLoadingEnabled" value="false"/>
        <setting name="defaultExecutorType" value="BATCH"/>
    </settings>
    <typeAliases>
        <typeAlias alias="User" type="App\Entity\User"/>
        <typeAlias alias="Post" type="App\Entity\Post"/>
    </typeAliases>
    <environments default="development">
        <environment id="development">
            <transactionManager type="JDBC"/>
            <dataSource type="SIMPLE">
                <property name="dsn" value="sqlite::memory:"/>
            </dataSource>
        </environment>
    </environments>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        $this->assertTrue($config->isCacheEnabled());
        $this->assertFalse($config->isLazyLoadingEnabled());
        $this->assertSame(ExecutorType::BATCH, $config->getDefaultExecutorType());
        $this->assertSame('App\Entity\User', $config->resolveTypeAlias('User'));
        $this->assertSame('App\Entity\Post', $config->resolveTypeAlias('Post'));
        $this->assertNotNull($config->getEnvironment('development'));
    }

    public function testParseWithEmptySettings(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <settings>
    </settings>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        // Should use defaults
        $this->assertTrue($config->isCacheEnabled());
        $this->assertSame(ExecutorType::SIMPLE, $config->getDefaultExecutorType());
    }

    public function testParseWithEmptyTypeAliases(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <typeAliases>
    </typeAliases>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        // Should still have default aliases
        $this->assertSame('string', $config->resolveTypeAlias('string'));
    }

    public function testParseWithPackageInTypeAliases(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <typeAliases>
        <package name="App\Entity"/>
        <typeAlias alias="User" type="App\Entity\User"/>
    </typeAliases>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        // Package is currently a placeholder, but should not cause errors
        $this->assertSame('App\Entity\User', $config->resolveTypeAlias('User'));
    }

    public function testParseWithPackageInMappers(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <mappers>
        <package name="App\Mapper"/>
    </mappers>
</configuration>
XML;

        $config = $this->parser->parseXml($xml);

        // Package scanning is not yet implemented, should not cause errors
        $this->assertInstanceOf(Configuration::class, $config);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
