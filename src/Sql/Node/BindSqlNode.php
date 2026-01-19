<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

use Touta\Ogam\Sql\DynamicContext;

/**
 * Bind SQL node for creating local variables.
 *
 * <bind name="pattern" value="'%' + name + '%'" />
 */
final class BindSqlNode implements SqlNode
{
    public function __construct(
        private readonly string $name,
        private readonly string $value,
    ) {}

    public function apply(DynamicContext $context): bool
    {
        $value = $context->evaluate($this->value);
        $context->bind($this->name, $value);

        return true;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
