<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

use Touta\Ogam\Sql\DynamicContext;

/**
 * A node containing static SQL text.
 */
final class TextSqlNode implements SqlNode
{
    public function __construct(
        private readonly string $text,
    ) {}

    public function apply(DynamicContext $context): bool
    {
        $context->appendSql($this->text);

        return true;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
