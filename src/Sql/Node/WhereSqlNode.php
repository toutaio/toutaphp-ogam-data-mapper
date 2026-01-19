<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

/**
 * Where SQL node.
 *
 * <where>
 *     <if test="...">AND column = #{value}</if>
 * </where>
 *
 * Automatically adds WHERE and removes leading AND/OR.
 */
final class WhereSqlNode extends TrimSqlNode
{
    public function __construct(SqlNode $contents)
    {
        parent::__construct(
            $contents,
            'WHERE ',
            'AND |OR ',
            '',
            '',
        );
    }
}
