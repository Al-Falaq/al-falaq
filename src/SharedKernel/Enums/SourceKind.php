<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Enums;

/**
 * What kind of dependency produced a CalculationSource.
 */
enum SourceKind: string
{
    case ALGORITHM = 'algorithm';
    case GENERATED_DATA = 'generated_data';
    case OFFICIAL_DATA = 'official_data';
    case CALLER_INPUT = 'caller_input';
}
