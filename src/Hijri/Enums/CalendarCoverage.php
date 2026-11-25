<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Enums;

/**
 * How a calendar derives its month starts.
 *
 * `ARITHMETIC_RULE` needs no table data; `GENERATED_TABLE` is shipped data
 * rebuilt by a pinned generator; `OFFICIAL_TABLE` would claim a published
 * authority dataset. No shipped calendar claims official status today.
 */
enum CalendarCoverage: string
{
    case ARITHMETIC_RULE = 'arithmetic_rule';
    case GENERATED_TABLE = 'generated_table';
    case OFFICIAL_TABLE = 'official_table';
}
