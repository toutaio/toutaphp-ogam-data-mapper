<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;

/**
 * Maps a single column to an object property.
 *
 * This attribute can be repeated on a method to define multiple column mappings.
 *
 * Example:
 * ```php
 * #[Select("SELECT id, user_name, email_address FROM users")]
 * #[Result(property: 'id', column: 'id')]
 * #[Result(property: 'username', column: 'user_name')]
 * #[Result(property: 'email', column: 'email_address')]
 * public function findAll(): array;
 *
 * #[Select("SELECT created_at FROM users WHERE id = :id")]
 * #[Result(
 *     property: 'createdAt',
 *     column: 'created_at',
 *     phpType: DateTimeImmutable::class,
 *     typeHandler: DateTimeHandler::class
 * )]
 * public function getCreatedAt(int $id): ?DateTimeImmutable;
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Result
{
    /**
     * @param string $property The name of the object property
     * @param string $column The name of the database column
     * @param string|null $phpType The PHP type for type conversion
     * @param string|null $typeHandler Custom type handler class name
     */
    public function __construct(
        public string $property,
        public string $column,
        public ?string $phpType = null,
        public ?string $typeHandler = null,
    ) {}
}
