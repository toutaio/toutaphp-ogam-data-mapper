<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

use Touta\Ogam\Sql\DynamicContext;

/**
 * A container node that holds multiple child nodes.
 */
final class MixedSqlNode implements SqlNode
{
    /**
     * @param list<SqlNode> $contents
     */
    public function __construct(
        private readonly array $contents,
    ) {}

    public function apply(DynamicContext $context): bool
    {
        $result = false;

        foreach ($this->contents as $node) {
            if ($node->apply($context)) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * @return list<SqlNode>
     */
    public function getContents(): array
    {
        return $this->contents;
    }
}
