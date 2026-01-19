<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * The type of SQL statement.
 */
enum StatementType: string
{
    case SELECT = 'select';
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case CALLABLE = 'callable';
}
