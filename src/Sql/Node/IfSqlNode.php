<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

use Touta\Ogam\Sql\DynamicContext;

/**
 * Conditional SQL node.
 *
 * <if test="expression">
 *     SQL content
 * </if>
 */
final class IfSqlNode implements SqlNode
{
    public function __construct(
        private readonly string $test,
        private readonly SqlNode $contents,
    ) {}

    public function apply(DynamicContext $context): bool
    {
        if ($context->evaluateBoolean($this->test)) {
            $this->contents->apply($context);

            return true;
        }

        return false;
    }

    public function getTest(): string
    {
        return $this->test;
    }

    public function getContents(): SqlNode
    {
        return $this->contents;
    }
}
