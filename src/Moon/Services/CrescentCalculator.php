<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\Services;

use AlFalaq\AlFalak\Moon\ValueObjects\CrescentGeometry;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

/**
 * Moon-sighting entry point: geometry at local sunset (M3).
 */
final class CrescentCalculator
{
    /**
     * Compute sun/moon geometry at sunset for one place and date (M3).
     *
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date of the evening observation.
     * @return CrescentGeometry Altitudes, elongation, age, lag, azimuth delta.
     */
    public function atSunset(Coordinates $coordinates, CivilDate $date): CrescentGeometry
    {
        throw new \LogicException('not implemented: M3 moon pending');
    }
}
