<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\ValueObjects\NightPortions;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;

/**
 * Fixed-twilight-angle high-latitude correction (M1).
 *
 * Pin pointer: CalculationParameters::night_portions.
 */
final class TwilightAngleCorrection implements HighLatitudeCorrection, IdentifiesCalculationSource
{
    /**
     * Registry id for this correction.
     */
    public const SOURCE_ID = 'prayer.high-latitude.twilight_angle';

    /**
     * Split the night at the configured twilight angle (M1).
     *
     * @param float $fajrAngle Method fajr angle (for TWILIGHT_ANGLE rule).
     * @param float|null $ishaAngle Method isha angle, null when interval-based.
     * @return NightPortions Fajr and Isha night fractions.
     */
    public function portions(float $fajrAngle, ?float $ishaAngle): NightPortions
    {
        if ($ishaAngle === null) {
            throw new ConfigurationException('TWILIGHT_ANGLE requires angle-based Isha.');
        }

        return new NightPortions($fajrAngle / 60.0, $ishaAngle / 60.0);
    }

    /**
     * Stable provenance identity for this correction.
     *
     * @return CalculationSource Algorithm source carrying SOURCE_ID.
     */
    public function calculationSource(): CalculationSource
    {
        return CalculationSource::algorithm(self::SOURCE_ID);
    }
}
