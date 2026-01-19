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

        if (!is_iterable($iterable)) {
            return false;
        }

        $items = \is_array($iterable) ? $iterable : iterator_to_array($iterable);

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

            // Generate unique parameter names for this iteration
            $uniqueNum = $context->getUniqueNumber();
            $itemKey = "__frch_{$this->item}_{$uniqueNum}";
            $indexKey = $this->index !== null ? "__frch_{$this->index}_{$uniqueNum}" : null;

            // Bind with unique names
            $context->bind($itemKey, $value);

            if ($indexKey !== null) {
                $context->bind($indexKey, $key);
            }

            // Create a child context to capture content SQL
            $childContext = new DynamicContext(
                $context->getConfiguration(),
                $context->getParameter(),
            );

            // Copy bindings to child
            foreach ($context->getBindings() as $name => $val) {
                $childContext->bind($name, $val);
            }

            // Bind the original item name for the content evaluation
            $childContext->bind($this->item, $value);

            if ($this->index !== null) {
                $childContext->bind($this->index, $key);
            }

            $this->contents->apply($childContext);

            // Get the content SQL and replace #{item} with #{uniqueItemKey}
            $contentSql = $childContext->getSql();
            $contentSql = str_replace('#{' . $this->item . '}', '#{' . $itemKey . '}', $contentSql);

            if ($this->index !== null) {
                $contentSql = str_replace('#{' . $this->index . '}', '#{' . $indexKey . '}', $contentSql);
            }

            $context->appendSql($contentSql);
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
