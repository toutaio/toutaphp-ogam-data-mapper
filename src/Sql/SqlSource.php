<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql;

use Touta\Ogam\Mapping\BoundSql;

/**
 * Source for generating SQL from templates with dynamic content.
 *
 * SqlSource instances are created by parsing XML mapper files
 * and are used to generate the final SQL with parameters.
 */
interface SqlSource
{
    /**
     * Generate the bound SQL for the given parameter.
     *
     * @param array<string, mixed>|object|null $parameter The parameters
     *
     * @return BoundSql The SQL with parameter mappings
     */
    public function getBoundSql(array|object|null $parameter): BoundSql;
}
