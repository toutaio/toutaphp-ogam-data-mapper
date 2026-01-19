<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\DataSource\PooledDataSource;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Session\DefaultSessionFactory;
use Touta\Ogam\Sql\DynamicSqlSource;
use Touta\Ogam\Sql\Node\TextSqlNode;
use Touta\Ogam\Transaction\ManagedTransactionFactory;

final class TypeHandlerIntegrationTest extends TestCase
{
    private Configuration $configuration;

    private DefaultSessionFactory $sessionFactory;

    private PooledDataSource $dataSource;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();

        $this->dataSource = new PooledDataSource('sqlite::memory:');
        $transactionFactory = new ManagedTransactionFactory();
        $environment = new Environment('default', $this->dataSource, $transactionFactory);

        $this->configuration->addEnvironment($environment);

        // Create test table with various types using pooled connection
        $pdo = $this->dataSource->getConnection();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS type_test (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                int_value INTEGER,
                float_value REAL,
                string_value TEXT,
                bool_value INTEGER,
                json_value TEXT,
                datetime_value TEXT
            )
        ');

        // Return connection to pool so session can reuse it
        $this->dataSource->releaseConnection($pdo);

        $this->addMappedStatements();
        $this->sessionFactory = new DefaultSessionFactory($this->configuration);
    }

    protected function tearDown(): void
    {
        $this->dataSource->clear();
    }

    public function testIntegerType(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->insert('TypeTest.insert', [
                'intValue' => 42,
                'floatValue' => 0.0,
                'stringValue' => '',
                'boolValue' => false,
                'jsonValue' => '{}',
                'datetimeValue' => '2024-01-01 00:00:00',
            ]);

            $result = $session->selectOne('TypeTest.findById', ['id' => 1]);

            $this->assertSame(42, (int) $result['int_value']);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testFloatType(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->insert('TypeTest.insert', [
                'intValue' => 0,
                'floatValue' => 3.14159,
                'stringValue' => '',
                'boolValue' => false,
                'jsonValue' => '{}',
                'datetimeValue' => '2024-01-01 00:00:00',
            ]);

            $result = $session->selectOne('TypeTest.findById', ['id' => 1]);

            $this->assertEqualsWithDelta(3.14159, (float) $result['float_value'], 0.00001);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testBooleanType(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->insert('TypeTest.insert', [
                'intValue' => 0,
                'floatValue' => 0.0,
                'stringValue' => '',
                'boolValue' => true,
                'jsonValue' => '{}',
                'datetimeValue' => '2024-01-01 00:00:00',
            ]);

            $result = $session->selectOne('TypeTest.findById', ['id' => 1]);

            $this->assertSame(1, (int) $result['bool_value']);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testJsonType(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $jsonData = ['name' => 'Test', 'values' => [1, 2, 3]];

            $session->insert('TypeTest.insert', [
                'intValue' => 0,
                'floatValue' => 0.0,
                'stringValue' => '',
                'boolValue' => false,
                'jsonValue' => json_encode($jsonData),
                'datetimeValue' => '2024-01-01 00:00:00',
            ]);

            $result = $session->selectOne('TypeTest.findById', ['id' => 1]);

            $decoded = json_decode($result['json_value'], true);
            $this->assertSame('Test', $decoded['name']);
            $this->assertSame([1, 2, 3], $decoded['values']);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testDateTimeType(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $dateTime = new DateTimeImmutable('2024-06-15 14:30:00');

            $session->insert('TypeTest.insert', [
                'intValue' => 0,
                'floatValue' => 0.0,
                'stringValue' => '',
                'boolValue' => false,
                'jsonValue' => '{}',
                'datetimeValue' => $dateTime->format('Y-m-d H:i:s'),
            ]);

            $result = $session->selectOne('TypeTest.findById', ['id' => 1]);

            $this->assertSame('2024-06-15 14:30:00', $result['datetime_value']);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testNullValues(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->insert('TypeTest.insertNullable', [
                'intValue' => null,
                'floatValue' => null,
                'stringValue' => null,
            ]);

            $result = $session->selectOne('TypeTest.findById', ['id' => 1]);

            $this->assertNull($result['int_value']);
            $this->assertNull($result['float_value']);
            $this->assertNull($result['string_value']);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    private function addMappedStatements(): void
    {
        // insert
        $this->configuration->addMappedStatement(new MappedStatement(
            'insert',
            'TypeTest',
            StatementType::INSERT,
            null,
            null,
            null,
            null,
            true,
            'id',
            null,
            0,
            0,
            null,
            new DynamicSqlSource(
                $this->configuration,
                new TextSqlNode('
                    INSERT INTO type_test (int_value, float_value, string_value, bool_value, json_value, datetime_value)
                    VALUES (#{intValue}, #{floatValue}, #{stringValue}, #{boolValue}, #{jsonValue}, #{datetimeValue})
                '),
            ),
        ));

        // insertNullable
        $this->configuration->addMappedStatement(new MappedStatement(
            'insertNullable',
            'TypeTest',
            StatementType::INSERT,
            null,
            null,
            null,
            null,
            true,
            'id',
            null,
            0,
            0,
            null,
            new DynamicSqlSource(
                $this->configuration,
                new TextSqlNode('
                    INSERT INTO type_test (int_value, float_value, string_value)
                    VALUES (#{intValue}, #{floatValue}, #{stringValue})
                '),
            ),
        ));

        // findById
        $this->configuration->addMappedStatement(new MappedStatement(
            'findById',
            'TypeTest',
            StatementType::SELECT,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            null,
            new DynamicSqlSource(
                $this->configuration,
                new TextSqlNode('SELECT * FROM type_test WHERE id = #{id}'),
            ),
        ));
    }
}
