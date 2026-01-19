<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

use Touta\Ogam\Sql\DynamicContext;

/**
 * A node in the dynamic SQL tree.
 *
 * Nodes are evaluated against a context to produce SQL.
 */
interface SqlNode
{
    /**
     * Apply this node to the context.
     *
     * @param DynamicContext $context The evaluation context
     *
     * @return bool Whether the node produced any output
     */
    public function apply(DynamicContext $context): bool;
}
