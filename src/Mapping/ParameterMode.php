<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Parameter mode for stored procedure parameters.
 */
enum ParameterMode: string
{
    case IN = 'IN';
    case OUT = 'OUT';
    case INOUT = 'INOUT';
}
