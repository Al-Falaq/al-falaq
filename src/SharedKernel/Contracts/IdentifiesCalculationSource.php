<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Contracts;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;

/**
 * Driven capability: a component can identify itself in calculation metadata.
 *
 * Built-in engines and strategies implement this; a wired component that does
 * not is reported as `custom.<component>` with an
 * `unidentified_calculation_source` warning rather than being guessed.
 */
interface IdentifiesCalculationSource
{
    /**
     * Stable registry identity for this component.
     *
     * @return CalculationSource Algorithm source with a documented id.
     */
    public function calculationSource(): CalculationSource;
}
