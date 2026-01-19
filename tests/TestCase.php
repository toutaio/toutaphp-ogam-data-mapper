<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests;

use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getFixturePath(string $name): string
    {
        return __DIR__ . '/fixtures/' . $name;
    }

    protected function createSqliteDataSource(): PDO
    {
        return new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
