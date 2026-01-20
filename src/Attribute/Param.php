<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;

/**
 * Specifies the parameter name for a method parameter.
 *
 * Use this attribute when the SQL parameter name differs from the PHP parameter name,
 * or when you need to explicitly specify the parameter binding.
 *
 * Example:
 * ```php
 * #[Select("SELECT * FROM users WHERE email LIKE :pattern")]
 * public function findByEmail(#[Param('pattern')] string $emailPattern): array;
 *
 * #[Select("SELECT * FROM users WHERE name = :name AND status = :status")]
 * public function findByNameAndStatus(
 *     #[Param('name')] string $userName,
 *     #[Param('status')] Status $userStatus,
 * ): array;
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Param
{
    /**
     * @param string $name The name of the parameter in the SQL statement
     */
    public function __construct(
        public string $name,
    ) {}
}
