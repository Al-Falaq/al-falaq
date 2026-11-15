<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Enums;

/**
 * Derived outcome status for one calculation (ranked lowest to highest).
 *
 * CalculationMetadata::create() derives this from the warning set; callers
 * never supply it directly.
 */
enum CalculationStatus: string
{
    case COMPLETE = 'complete';
    case EXTRAPOLATED = 'extrapolated';
    case FALLBACK_APPLIED = 'fallback_applied';
    case INDETERMINATE = 'indeterminate';
    case DECISION_REQUIRED = 'decision_required';
}
