<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\ValueObjects;

final readonly class CrescentGeometry
{
    public function __construct(
        public float $sunAltitude,
        public float $moonAltitude,
        public float $elongationDeg,
        public float $moonAgeHours,
        public float $lagMinutes,
        public float $deltaAzimuthDeg,
        public float $illuminatedFraction,
    ) {}
}
