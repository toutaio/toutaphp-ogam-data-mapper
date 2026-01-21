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
 * IMPORTANT: This is a forward-only cursor. PDOStatement cannot be rewound
 * once fetching has started. Calling rewind() after iteration will throw
 * an exception. If you need to iterate multiple times, fetch all results
 * into an array first.
 *
 * @template T
 *
 * @implements CursorInterface<T>
 */
final class PdoCursor implements CursorInterface
{
    private bool $closed = false;

    private int $index = -1;

    private bool $iterationStarted = false;

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

        /** @phpstan-ignore-next-line When no hydrator is provided, returns the raw array instead of type T */
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

        // Mark that iteration has started (after the initial rewind)
        $this->iterationStarted = true;

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

        // PDOStatement cannot be rewound - it's forward-only
        // Throw an exception if rewind is called after iteration has started
        if ($this->iterationStarted) {
            throw new \RuntimeException(
                'Cannot rewind cursor: PDOStatement is forward-only. ' .
                'If you need to iterate multiple times, fetch all results into an array first.'
            );
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
