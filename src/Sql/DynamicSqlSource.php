<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql;

use ReflectionClass;
use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Sql\Node\SqlNode;

/**
 * Dynamic SQL source for SQL with conditional/iterative elements.
 *
 * Evaluates dynamic SQL nodes at runtime based on parameter values.
 */
final class DynamicSqlSource implements SqlSource
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly SqlNode $rootNode,
    ) {}

    public function getBoundSql(array|object|null $parameter): BoundSql
    {
        $context = new DynamicContext($this->configuration, $parameter);

        $this->rootNode->apply($context);

        // Merge context bindings with original parameter for foreach support
        $mergedParameter = $this->mergeBindings($parameter, $context->getBindings());

        $sqlBuilder = new SqlSourceBuilder($this->configuration);

        return $sqlBuilder->parse($context->getSql(), $mergedParameter);
    }

    public function getRootNode(): SqlNode
    {
        return $this->rootNode;
    }

    /**
     * Merge context bindings with the original parameter.
     *
     * @param array<string, mixed>|object|null $parameter
     * @param array<string, mixed> $bindings
     *
     * @return array<string, mixed>
     */
    private function mergeBindings(array|object|null $parameter, array $bindings): array
    {
        $result = [];

        if (\is_array($parameter)) {
            $result = $parameter;
        } elseif (\is_object($parameter)) {
            // Convert object to array
            $reflection = new ReflectionClass($parameter);

            foreach ($reflection->getProperties() as $prop) {
                $prop->setAccessible(true);
                $result[$prop->getName()] = $prop->getValue($parameter);
            }
        }

        // Merge bindings (bindings take precedence)
        return array_merge($result, $bindings);
    }
}
