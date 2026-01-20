<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;

/**
 * Groups multiple Result mappings in a single attribute.
 *
 * Alternative to using multiple #[Result] attributes when you prefer to define
 * all mappings in one place.
 *
 * Example:
 * ```php
 * #[Select("SELECT id, user_name FROM users WHERE id = :id")]
 * #[Results([
 *     new Result(property: 'id', column: 'id'),
 *     new Result(property: 'name', column: 'user_name'),
 * ])]
 * public function findById(int $id): ?User;
 *
 * #[Select("SELECT id, username, email, status FROM users WHERE status = :status")]
 * #[Results([
 *     new Result(property: 'id', column: 'id'),
 *     new Result(property: 'username', column: 'username'),
 *     new Result(property: 'email', column: 'email'),
 *     new Result(property: 'status', column: 'status', typeHandler: StatusHandler::class),
 * ])]
 * public function findByStatus(Status $status): array;
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Results
{
    /**
     * @param array<Result> $value Array of Result mappings
     */
    public function __construct(
        public array $value,
    ) {}
}
