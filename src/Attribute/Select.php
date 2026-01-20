<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;
use Touta\Ogam\Mapping\FetchType;

/**
 * Marks a method as a SELECT statement.
 *
 * Example:
 * ```php
 * #[Select("SELECT * FROM users WHERE id = :id")]
 * public function findById(int $id): ?User;
 *
 * #[Select(
 *     sql: "SELECT * FROM users WHERE status = :status",
 *     resultMap: "fullUserResult",
 *     timeout: 30
 * )]
 * public function findByStatus(Status $status): array;
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Select
{
    /**
     * @param string $sql The SQL SELECT statement to execute
     * @param string|null $resultMap The ID of a result map to use for mapping
     * @param string|null $resultType The fully qualified class name for automatic mapping
     * @param int $timeout The statement timeout in seconds (0 = no timeout)
     * @param FetchType $fetchType The fetch strategy (EAGER or LAZY)
     */
    public function __construct(
        public string $sql,
        public ?string $resultMap = null,
        public ?string $resultType = null,
        public int $timeout = 0,
        public FetchType $fetchType = FetchType::EAGER,
    ) {}
}
