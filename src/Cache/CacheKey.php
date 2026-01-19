<?php

declare(strict_types=1);

namespace Touta\Ogam\Cache;

/**
 * A cache key that uniquely identifies a cached query result.
 *
 * The key is composed of:
 * - Statement ID
 * - Parameter values
 * - Row bounds (offset/limit)
 */
final class CacheKey
{
    private string $hash;

    /**
     * @param string $statementId The mapped statement ID
     * @param array<string, mixed> $parameters The query parameters
     * @param int $offset The row offset
     * @param int $limit The row limit
     */
    public function __construct(
        private readonly string $statementId,
        private readonly array $parameters,
        private readonly int $offset = 0,
        private readonly int $limit = 0,
    ) {
        $this->hash = $this->computeHash();
    }

    public function getStatementId(): string
    {
        return $this->statementId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Get the cache key as a string.
     */
    public function toString(): string
    {
        return $this->hash;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check if this key equals another.
     */
    public function equals(self $other): bool
    {
        return $this->hash === $other->hash;
    }

    private function computeHash(): string
    {
        $data = [
            'statement' => $this->statementId,
            'parameters' => $this->serializeParameters($this->parameters),
            'offset' => $this->offset,
            'limit' => $this->limit,
        ];

        return 'ogam:' . \hash('xxh3', \serialize($data));
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function serializeParameters(array $parameters): array
    {
        $result = [];

        foreach ($parameters as $key => $value) {
            $result[$key] = $this->serializeValue($value);
        }

        return $result;
    }

    private function serializeValue(mixed $value): mixed
    {
        if (\is_object($value)) {
            if ($value instanceof \DateTimeInterface) {
                return ['__datetime' => $value->format('Y-m-d H:i:s.u')];
            }

            if ($value instanceof \BackedEnum) {
                return ['__enum' => $value::class, 'value' => $value->value];
            }

            if ($value instanceof \UnitEnum) {
                return ['__enum' => $value::class, 'name' => $value->name];
            }

            return ['__object' => \spl_object_id($value)];
        }

        if (\is_array($value)) {
            return \array_map($this->serializeValue(...), $value);
        }

        return $value;
    }
}
