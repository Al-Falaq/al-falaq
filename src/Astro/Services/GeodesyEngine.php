<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\Services;

/** @internal */
final class GeodesyEngine
{
    /** @return array{direction: float, distanceKm: float} */
    public function inverse(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        throw new \LogicException('not implemented: M2 geodesy pending');
    }
}
