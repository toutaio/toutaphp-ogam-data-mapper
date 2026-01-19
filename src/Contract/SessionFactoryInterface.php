<?php

declare(strict_types=1);

namespace Touta\Ogam\Contract;

use Touta\Ogam\Configuration;
use Touta\Ogam\Executor\ExecutorType;

/**
 * Factory for creating Session instances.
 *
 * A SessionFactory is created once per application (typically at startup)
 * and is used to open sessions for database operations.
 *
 * SessionFactory instances are immutable and thread-safe.
 */
interface SessionFactoryInterface
{
    /**
     * Open a new session with default settings.
     *
     * @return SessionInterface A new session
     */
    public function openSession(): SessionInterface;

    /**
     * Open a new session with auto-commit enabled.
     *
     * Each statement will be committed immediately.
     *
     * @return SessionInterface A new session
     */
    public function openSessionWithAutoCommit(): SessionInterface;

    /**
     * Open a new session with a specific executor type.
     *
     * @param ExecutorType $executorType The executor type to use
     *
     * @return SessionInterface A new session
     */
    public function openSessionWithExecutor(ExecutorType $executorType): SessionInterface;

    /**
     * Get the configuration used by this factory.
     */
    public function getConfiguration(): Configuration;
}
