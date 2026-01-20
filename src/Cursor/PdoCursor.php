<?php

declare(strict_types=1);

namespace Touta\Ogam\Cursor;

use Closure;
use PDO;
use PDOStatement;

/**
 * PDO-based cursor for lazy iteration over query results.
 *
 * This cursor fetches rows one at a time from the database,
 * making it suitable for processing large result sets without
 * consuming excessive memory.
 *
 * @template T
 *
 * @implements CursorInterface<T>
 */
final class PdoCursor implements CursorInterface
{
    private bool $closed = false;

    private int $index = -1;

    /** @var array<string, mixed>|false|null */
    private array|false|null $currentRow = null;

    /**
     * @param PDOStatement $statement The executed PDO statement
     * @param Closure|null $hydrator Optional hydrator to transform rows
     */
    public function __construct(
        private readonly PDOStatement $statement,
        private readonly ?Closure $hydrator = null,
    ) {}

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return T|null
     */
    public function current(): mixed
    {
        if ($this->closed || $this->currentRow === false || $this->currentRow === null) {
            return null;
        }

        if ($this->hydrator !== null) {
            /** @var T */
            return ($this->hydrator)($this->currentRow);
        }

        /** @phpstan-ignore-next-line Generic type T defaults to array<string, mixed> when no hydrator */
        return $this->currentRow;
    }

    public function key(): int
    {
        return $this->index;
    }

    public function next(): void
    {
        if ($this->closed) {
            return;
        }

        /** @var array<string, mixed>|false $row */
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        $this->currentRow = $row;

        if ($this->currentRow !== false) {
            $this->index++;
        }
    }

    public function rewind(): void
    {
        if ($this->closed) {
            $this->currentRow = null;

            return;
        }

        // Reset index
        $this->index = 0;

        // Fetch first row
        /** @var array<string, mixed>|false $row */
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        $this->currentRow = $row;
    }

    public function valid(): bool
    {
        return !$this->closed && $this->currentRow !== false && $this->currentRow !== null;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->statement->closeCursor();
        $this->closed = true;
        $this->currentRow = null;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }
}
