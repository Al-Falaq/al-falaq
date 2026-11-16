<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Enums;

/**
 * Stable machine identifiers for metadata warnings.
 *
 * Human-readable messages live on CalculationWarning; only these values are
 * part of the wire contract.
 */
enum WarningCode: string
{
    case DELTA_T_EXTRAPOLATED = 'delta_t_extrapolated';
    case POLAR_FALLBACK_APPLIED = 'polar_fallback_applied';
    case SUNSET_FRAME_MAGHRIB_FALLBACK = 'sunset_frame_maghrib_fallback';
    case MOONSET_INDETERMINATE = 'moonset_indeterminate';
    case CRESCENT_CRITERION_IS_NOT_OBSERVATION = 'crescent_criterion_is_not_observation';
    case GENERATED_CALENDAR_TABLE = 'generated_calendar_table';
    case TABULAR_CALENDAR_ESTIMATE = 'tabular_calendar_estimate';
    case UNIDENTIFIED_CALCULATION_SOURCE = 'unidentified_calculation_source';
}
