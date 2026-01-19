<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

use Touta\Ogam\Sql\DynamicContext;

/**
 * Iteration SQL node.
 *
 * <foreach collection="list" item="item" index="idx" open="(" separator="," close=")">
 *     #{item}
 * </foreach>
 */
final class ForEachSqlNode implements SqlNode
{
    public function __construct(
        private readonly string $collection,
        private readonly string $item,
        private readonly ?string $index,
        private readonly SqlNode $contents,
        private readonly string $open = '',
        private readonly string $close = '',
        private readonly string $separator = '',
    ) {}

    public function apply(DynamicContext $context): bool
    {
        $iterable = $context->evaluate($this->collection);

        if (!\is_iterable($iterable)) {
            return false;
        }

        $items = \is_array($iterable) ? $iterable : \iterator_to_array($iterable);

        if ($items === []) {
            return false;
        }

        $context->appendSql($this->open);

        $first = true;

        foreach ($items as $key => $value) {
            if (!$first) {
                $context->appendSql($this->separator);
            }

            $first = false;

            // Bind the item and index
            $context->bind($this->item, $value);

            if ($this->index !== null) {
                $context->bind($this->index, $key);
            }

            $this->contents->apply($context);
        }

        $context->appendSql($this->close);

        return true;
    }

    public function getCollection(): string
    {
        return $this->collection;
    }

    public function getItem(): string
    {
        return $this->item;
    }

    public function getIndex(): ?string
    {
        return $this->index;
    }

    public function getContents(): SqlNode
    {
        return $this->contents;
    }

    public function getOpen(): string
    {
        return $this->open;
    }

    public function getClose(): string
    {
        return $this->close;
    }

    public function getSeparator(): string
    {
        return $this->separator;
    }
}
