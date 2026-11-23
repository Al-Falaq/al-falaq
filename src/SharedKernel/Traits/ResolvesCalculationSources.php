<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Traits;

use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationWarning;

/**
 * Shared custom-component fallback for calculation-source lists.
 *
 * One owner for the `custom.<component>` id plus
 * `unidentified_calculation_source` warning, so every context reports a
 * wired component without `IdentifiesCalculationSource` the same way.
 */
trait ResolvesCalculationSources
{
    /**
     * Identity for a wired component, or `custom.<name>` plus a warning.
     *
     * @param object $component Wired strategy or engine.
     * @param string $name Registry slot name (solar, isha, geodesy, …).
     * @param list<CalculationWarning> $warnings Warning sink, appended in place.
     * @return CalculationSource Identified source or the custom fallback.
     */
    private static function componentSource(object $component, string $name, array &$warnings): CalculationSource
    {
        if ($component instanceof IdentifiesCalculationSource) {
            return $component->calculationSource();
        }

        $id = 'custom.' . $name;

        $warnings[] = new CalculationWarning(
            WarningCode::UNIDENTIFIED_CALCULATION_SOURCE,
            sprintf('The %s component does not implement IdentifiesCalculationSource.', $name),
            ['component' => $name, 'source_id' => $id],
        );

        return CalculationSource::algorithm($id);
    }
}
