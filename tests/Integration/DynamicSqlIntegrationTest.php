<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\DataSource\PooledDataSource;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Session\DefaultSessionFactory;
use Touta\Ogam\Sql\DynamicSqlSource;
use Touta\Ogam\Sql\Node\ForEachSqlNode;
use Touta\Ogam\Sql\Node\IfSqlNode;
use Touta\Ogam\Sql\Node\MixedSqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;
use Touta\Ogam\Sql\Node\WhereSqlNode;
use Touta\Ogam\Transaction\ManagedTransactionFactory;

final class DynamicSqlIntegrationTest extends TestCase
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

        // Create test table and data using pooled connection
        $pdo = $this->dataSource->getConnection();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                category TEXT,
                price REAL,
                active INTEGER DEFAULT 1
            )
        ');

        $pdo->exec("INSERT INTO products (name, category, price, active) VALUES ('Laptop', 'Electronics', 999.99, 1)");
        $pdo->exec("INSERT INTO products (name, category, price, active) VALUES ('Phone', 'Electronics', 599.99, 1)");
        $pdo->exec("INSERT INTO products (name, category, price, active) VALUES ('Desk', 'Furniture', 299.99, 1)");
        $pdo->exec("INSERT INTO products (name, category, price, active) VALUES ('Chair', 'Furniture', 149.99, 0)");

        // Return connection to pool so session can reuse it
        $this->dataSource->releaseConnection($pdo);

        $this->addMappedStatements();
        $this->sessionFactory = new DefaultSessionFactory($this->configuration);
    }

    protected function tearDown(): void
    {
        $this->dataSource->clear();
    }

    public function testDynamicWhereClause(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            // Search with category filter
            $products = $session->selectList('ProductMapper.search', ['category' => 'Electronics']);
            $this->assertCount(2, $products);

            // Search with price filter
            $products = $session->selectList('ProductMapper.search', ['minPrice' => 500]);
            $this->assertCount(2, $products);

            // Search with both filters
            $products = $session->selectList('ProductMapper.search', [
                'category' => 'Electronics',
                'minPrice' => 700,
            ]);
            $this->assertCount(1, $products);
            $this->assertSame('Laptop', $products[0]['name']);

            // Search with no filters
            $products = $session->selectList('ProductMapper.search', []);
            $this->assertCount(4, $products);
        } finally {
            $session->close();
        }
    }

    public function testForeachInClause(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $products = $session->selectList('ProductMapper.findByIds', ['ids' => [1, 3]]);

            $this->assertCount(2, $products);

            $names = array_column($products, 'name');
            $this->assertContains('Laptop', $names);
            $this->assertContains('Desk', $names);
        } finally {
            $session->close();
        }
    }

    public function testForeachWithEmptyArray(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            // This should return all products since the IN clause is empty
            $products = $session->selectList('ProductMapper.findByCategories', ['categories' => []]);

            // With empty array, the WHERE clause is stripped
            $this->assertCount(4, $products);
        } finally {
            $session->close();
        }
    }

    public function testConditionalActiveFilter(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            // With active filter
            $products = $session->selectList('ProductMapper.searchWithActive', [
                'activeOnly' => true,
            ]);
            $this->assertCount(3, $products);

            // Without active filter
            $products = $session->selectList('ProductMapper.searchWithActive', [
                'activeOnly' => false,
            ]);
            $this->assertCount(4, $products);
        } finally {
            $session->close();
        }
    }

    private function addMappedStatements(): void
    {
        // search with dynamic where
        $this->configuration->addMappedStatement(new MappedStatement(
            'search',
            'ProductMapper',
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
                new MixedSqlNode([
                    new TextSqlNode(' SELECT * FROM products '),
                    new WhereSqlNode(
                        new MixedSqlNode([
                            new IfSqlNode('category', new TextSqlNode(' AND category = #{category} ')),
                            new IfSqlNode('minPrice', new TextSqlNode(' AND price >= #{minPrice} ')),
                            new IfSqlNode('name', new TextSqlNode(' AND name LIKE #{name} ')),
                        ]),
                    ),
                ]),
            ),
        ));

        // findByIds with foreach
        $this->configuration->addMappedStatement(new MappedStatement(
            'findByIds',
            'ProductMapper',
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
                new MixedSqlNode([
                    new TextSqlNode(' SELECT * FROM products WHERE id IN '),
                    new ForEachSqlNode(
                        'ids',
                        'id',
                        null,
                        new TextSqlNode('#{id}'),
                        '(',
                        ')',
                        ', ',
                    ),
                ]),
            ),
        ));

        // findByCategories with foreach in where
        $this->configuration->addMappedStatement(new MappedStatement(
            'findByCategories',
            'ProductMapper',
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
                new MixedSqlNode([
                    new TextSqlNode(' SELECT * FROM products '),
                    new WhereSqlNode(
                        new MixedSqlNode([
                            new IfSqlNode('categories', new MixedSqlNode([
                                new TextSqlNode(' AND category IN '),
                                new ForEachSqlNode(
                                    'categories',
                                    'cat',
                                    null,
                                    new TextSqlNode('#{cat}'),
                                    '(',
                                    ')',
                                    ', ',
                                ),
                            ])),
                        ]),
                    ),
                ]),
            ),
        ));

        // searchWithActive
        $this->configuration->addMappedStatement(new MappedStatement(
            'searchWithActive',
            'ProductMapper',
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
                new MixedSqlNode([
                    new TextSqlNode(' SELECT * FROM products '),
                    new WhereSqlNode(
                        new MixedSqlNode([
                            new IfSqlNode('activeOnly', new TextSqlNode(' AND active = 1 ')),
                        ]),
                    ),
                ]),
            ),
        ));
    }
}
