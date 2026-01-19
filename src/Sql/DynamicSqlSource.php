<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql;

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

        $sqlBuilder = new SqlSourceBuilder($this->configuration);

        return $sqlBuilder->parse($context->getSql(), $parameter);
    }

    public function getRootNode(): SqlNode
    {
        return $this->rootNode;
    }
}
