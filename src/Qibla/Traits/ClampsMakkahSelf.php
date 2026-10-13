<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\Traits;

use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;

/**
 * Degenerate Makkah-self clamp shared by the geodesy adapters (single owner).
 *
 * Below GeodesyCalculator::SELF_DISTANCE_KM the raw bearing is
 * construction-defined and never leaks out (§5.3).
 */
trait ClampsMakkahSelf
{
    /**
     * Clamp a raw inverse solution at Makkah-self to direction + distance 0.0.
     *
     * @param array{direction: float, distanceKm: float} $solved Raw bearing + distance.
     * @return array{direction: float, distanceKm: float} Clamped bearing + distance.
     */
    private static function clampMakkahSelf(array $solved): array
    {
        if ($solved['distanceKm'] < GeodesyCalculator::SELF_DISTANCE_KM) {
            return ['direction' => 0.0, 'distanceKm' => 0.0];
        }

        return $solved;
    }
}
