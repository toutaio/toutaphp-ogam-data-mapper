<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

use Touta\Ogam\Sql\DynamicContext;

/**
 * Trim SQL node.
 *
 * <trim prefix="WHERE" prefixOverrides="AND |OR " suffix="" suffixOverrides="">
 *     content
 * </trim>
 */
class TrimSqlNode implements SqlNode
{
    /** @var list<string> */
    private readonly array $prefixesToOverride;

    /** @var list<string> */
    private readonly array $suffixesToOverride;

    public function __construct(
        private readonly SqlNode $contents,
        private readonly string $prefix = '',
        string $prefixOverrides = '',
        private readonly string $suffix = '',
        string $suffixOverrides = '',
    ) {
        $this->prefixesToOverride = $this->parseOverrides($prefixOverrides);
        $this->suffixesToOverride = $this->parseOverrides($suffixOverrides);
    }

    public function apply(DynamicContext $context): bool
    {
        // Create a child context to capture the content
        $childContext = new DynamicContext(
            $context->getConfiguration(),
            $context->getParameter(),
        );

        // Copy bindings to child
        foreach ($context->getBindings() as $name => $value) {
            $childContext->bind($name, $value);
        }

        $this->contents->apply($childContext);

        $sql = trim($childContext->getSql());

        if ($sql === '') {
            return false;
        }

        // Apply prefix overrides
        $sql = $this->applyPrefixOverrides($sql);

        // Apply suffix overrides
        $sql = $this->applySuffixOverrides($sql);

        // Apply prefix and suffix
        $result = $this->prefix . $sql . $this->suffix;

        $context->appendSql($result);

        return true;
    }

    public function getContents(): SqlNode
    {
        return $this->contents;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * @return list<string>
     */
    private function parseOverrides(string $overrides): array
    {
        if ($overrides === '') {
            return [];
        }

        return array_map(
            static fn(string $s) => strtoupper(trim($s)),
            explode('|', $overrides),
        );
    }

    private function applyPrefixOverrides(string $sql): string
    {
        $upper = strtoupper($sql);

        foreach ($this->prefixesToOverride as $override) {
            if (str_starts_with($upper, $override)) {
                $sql = substr($sql, \strlen($override));
                $sql = ltrim($sql);

                break;
            }
        }

        return $sql;
    }

    private function applySuffixOverrides(string $sql): string
    {
        $upper = strtoupper($sql);

        foreach ($this->suffixesToOverride as $override) {
            $trimmedOverride = rtrim($override);

            if (str_ends_with($upper, $trimmedOverride)) {
                $sql = substr($sql, 0, -\strlen($trimmedOverride));
                $sql = rtrim($sql);

                break;
            }
        }

        return $sql;
    }
}
