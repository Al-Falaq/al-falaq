<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\Strategies;

use AlFalaq\AlFalaq\Moon\Contracts\CrescentEvaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentVerdict;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;

/**
 * Neo-MABIMS-2021 criterion: alt ≥ 3°, elongation ≥ 6.4° (no age gate).
 */
final class NeoMabims2021Evaluator implements CrescentEvaluator, IdentifiesCalculationSource
{
    /**
     * Registry id for the Neo-MABIMS-2021 criterion.
     */
    public const SOURCE_ID = 'moon.criterion.neo-mabims-2021';

    /**
     * Apply the 2021 thresholds.
     *
     * @param CrescentGeometry $geometry Sun/moon geometry at local sunset.
     * @return CrescentVerdict 'neomabims2021' verdict + elongation score.
     */
    public function evaluate(CrescentGeometry $geometry): CrescentVerdict
    {
        $visible = $geometry->moonAltitude >= 3.0 && $geometry->elongationDeg >= 6.4;

        return new CrescentVerdict('neomabims2021', $visible, $geometry->elongationDeg, $visible ? 'visible' : 'not-visible');
    }

    /**
     * Stable provenance identity for this criterion.
     *
     * @return CalculationSource Algorithm source carrying SOURCE_ID.
     */
    public function calculationSource(): CalculationSource
    {
        return CalculationSource::algorithm(self::SOURCE_ID);
    }
}
