<?php

declare(strict_types=1);

namespace Touta\Ogam\Type\Handler;

use PDO;
use PDOStatement;
use Touta\Ogam\Type\BaseTypeHandler;

/**
 * Type handler for JSON values.
 *
 * Converts between PHP arrays/objects and JSON strings.
 */
final class JsonHandler extends BaseTypeHandler
{
    /**
     * @param bool $associative Whether to decode JSON as associative arrays (default: true)
     * @param int $encodeFlags JSON encode flags
     * @param int $decodeFlags JSON decode flags
     * @param int $depth Maximum depth
     */
    public function __construct(
        private readonly bool $associative = true,
        private readonly int $encodeFlags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        private readonly int $decodeFlags = JSON_THROW_ON_ERROR,
        private readonly int $depth = 512,
    ) {}

    public function getPhpType(): ?string
    {
        return 'array';
    }

    protected function setNonNullParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void {
        if (\is_string($value)) {
            // Already JSON string
            $json = $value;
        } else {
            $json = json_encode($value, $this->encodeFlags, $this->depth);
        }

        $statement->bindValue($index, $json, PDO::PARAM_STR);
    }

    protected function getNonNullResult(mixed $value): mixed
    {
        if (\is_array($value) || \is_object($value)) {
            // Already decoded
            return $value;
        }

        if (!\is_string($value)) {
            return $value;
        }

        return json_decode($value, $this->associative, $this->depth, $this->decodeFlags);
    }
}
