<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\ValueObjects;

/** @internal */
final readonly class LunarPosition
{
    public function __construct(
        public float $altitudeDeg,
        public float $azimuthDeg,
        public float $ageHours,
    ) {}
}
