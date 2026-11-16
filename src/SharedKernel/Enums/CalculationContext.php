<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Enums;

/**
 * The public calculation contexts that can produce provenance metadata.
 */
enum CalculationContext: string
{
    case PRAYER = 'prayer';
    case QIBLA = 'qibla';
    case MOON = 'moon';
    case HIJRI = 'hijri';
}
