<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Node;

/**
 * Set SQL node for UPDATE statements.
 *
 * <set>
 *     <if test="username != null">username = #{username},</if>
 *     <if test="email != null">email = #{email},</if>
 * </set>
 *
 * Automatically adds SET and removes trailing commas.
 */
final class SetSqlNode extends TrimSqlNode
{
    public function __construct(SqlNode $contents)
    {
        parent::__construct(
            $contents,
            'SET ',
            '',
            '',
            ',',
        );
    }
}
