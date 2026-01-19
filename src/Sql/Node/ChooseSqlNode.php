<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

use Touta\Ogam\Sql\DynamicContext;

/**
 * Choose/when/otherwise SQL node.
 *
 * <choose>
 *     <when test="expression">SQL</when>
 *     <when test="expression">SQL</when>
 *     <otherwise>SQL</otherwise>
 * </choose>
 */
final class ChooseSqlNode implements SqlNode
{
    /**
     * @param list<IfSqlNode> $whenNodes
     */
    public function __construct(
        private readonly array $whenNodes,
        private readonly ?SqlNode $otherwise = null,
    ) {}

    public function apply(DynamicContext $context): bool
    {
        foreach ($this->whenNodes as $whenNode) {
            if ($whenNode->apply($context)) {
                return true;
            }
        }

        if ($this->otherwise !== null) {
            $this->otherwise->apply($context);

            return true;
        }

        return false;
    }

    /**
     * @return list<IfSqlNode>
     */
    public function getWhenNodes(): array
    {
        return $this->whenNodes;
    }

    public function getOtherwise(): ?SqlNode
    {
        return $this->otherwise;
    }
}
