<?php

declare(strict_types=1);

namespace Touta\Ogam\Cursor;

use Iterator;

/**
 * Interface for cursors that allow lazy iteration over large result sets.
 *
 * Cursors provide memory-efficient iteration by fetching rows one at a time
 * instead of loading all results into memory at once.
 *
 * @template T
 *
 * @extends Iterator<int, T>
 */
interface CursorInterface extends Iterator
{
    /**
     * Close the cursor and release resources.
     *
     * After closing, the cursor can no longer be iterated.
     */
    public function close(): void;

    /**
     * Check if the cursor has been closed.
     */
    public function isClosed(): bool;
}
