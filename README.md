# Touta Ogam - Data Mapper for PHP

[![CI](https://github.com/toutaio/toutaphp-ogam/actions/workflows/ci.yml/badge.svg)](https://github.com/toutaio/toutaphp-ogam/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**Ogam** (ᚑᚌᚐᚋ) is a MyBatis-inspired SQL mapping framework for PHP. Write the SQL you want, map results to objects automatically.

> **Ogam** is the ancient Celtic alphabet used by Druids to inscribe sacred knowledge. Like Ogam mapped symbols to meaning, this library maps SQL results to PHP objects.

## Features

- **SQL-First**: Write exactly the SQL you want, optimized for your use case
- **Declarative Mapping**: Configure how results become objects via XML or attributes
- **Dynamic SQL**: Build queries conditionally with `<if>`, `<foreach>`, `<where>`, `<set>`
- **Type Handlers**: Automatic conversion between PHP and SQL types (DateTime, Enums, JSON)
- **Zero Magic**: Every SQL executed is visible and predictable
- **Framework Agnostic**: Works standalone or with Symfony/Laravel

## Requirements

- PHP 8.3+
- PDO extension
- MySQL, PostgreSQL, or SQLite

## Installation

```bash
composer require touta/ogam
```

## Quick Start

### 1. Define Your Entity

```php
<?php

class User
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public Status $status,
        public DateTimeImmutable $createdAt,
    ) {}
}
```

### 2. Create XML Mapper

```xml
<!-- mappers/UserMapper.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<mapper namespace="App\Mapper\UserMapper">

    <select id="findById" resultType="App\Entity\User">
        SELECT id, username, email, status, created_at
        FROM users
        WHERE id = #{id}
    </select>

    <select id="findByStatus" resultType="App\Entity\User">
        SELECT id, username, email, status, created_at
        FROM users
        <where>
            <if test="status != null">
                AND status = #{status}
            </if>
        </where>
    </select>

    <insert id="insert" useGeneratedKeys="true" keyProperty="id">
        INSERT INTO users (username, email, status, created_at)
        VALUES (#{username}, #{email}, #{status}, #{createdAt})
    </insert>

</mapper>
```

### 3. Define Mapper Interface

```php
<?php

use Touta\Ogam\Attribute\Mapper;

#[Mapper(resource: 'mappers/UserMapper.xml')]
interface UserMapper
{
    public function findById(int $id): ?User;
    public function findByStatus(?Status $status): array;
    public function insert(User $user): int;
}
```

### 4. Use the Mapper

```php
<?php

use Touta\Ogam\SessionFactoryBuilder;

// Build session factory
$factory = (new SessionFactoryBuilder())
    ->withXmlConfig('config/ogam.xml')
    ->build();

// Open session
$session = $factory->openSession();

// Get mapper
$userMapper = $session->getMapper(UserMapper::class);

// Query
$user = $userMapper->findById(1);

// Insert
$newUser = new User(0, 'john', 'john@example.com', Status::ACTIVE, new DateTimeImmutable());
$userMapper->insert($newUser);
echo $newUser->id; // Generated ID

// Commit and close
$session->commit();
$session->close();
```

## Documentation

- [Configuration Guide](docs/configuration.md)
- [SQL Mapping](docs/sql-mapping.md)
- [Dynamic SQL](docs/dynamic-sql.md)
- [Result Mapping](docs/result-mapping.md)
- [Type Handlers](docs/type-handlers.md)
- [CLI Tool](docs/cli.md)

## Parameter Syntax

Ogam uses MyBatis-style parameter syntax:

```xml
<!-- Value binding (safe, parameterized) -->
SELECT * FROM users WHERE id = #{id} AND status = #{status}

<!-- Identifier substitution (for column/table names) -->
SELECT * FROM users ORDER BY ${orderColumn} ${orderDir}
```

## Dynamic SQL

```xml
<select id="search" resultType="User">
    SELECT * FROM users
    <where>
        <if test="name != null">
            AND name LIKE #{name}
        </if>
        <if test="email != null">
            AND email = #{email}
        </if>
        <if test="statuses != null">
            AND status IN
            <foreach collection="statuses" item="s" open="(" separator="," close=")">
                #{s}
            </foreach>
        </if>
    </where>
</select>
```

## Why Ogam?

| vs Doctrine | Ogam Advantage |
|-------------|----------------|
| Hydration overhead | Constructor injection, no reflection |
| Hidden SQL | Full SQL visibility and control |
| Learning curve | Write SQL you know, not DQL |

| vs Eloquent | Ogam Advantage |
|-------------|----------------|
| Performance | 2-3x faster (no Active Record overhead) |
| SQL control | Write optimized SQL directly |
| Testability | Clean separation, no database for unit tests |

## Part of Toutā Framework

Ogam is part of the [Toutā Framework](https://github.com/toutaio) ecosystem:

- **Toutā**: Core framework
- **Cosan**: HTTP router
- **Fíth**: Template engine
- **Nasc**: Dependency injection
- **Ogam**: Data mapping (this library)

Each component works standalone or together.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

MIT License. See [LICENSE](LICENSE) for details.
