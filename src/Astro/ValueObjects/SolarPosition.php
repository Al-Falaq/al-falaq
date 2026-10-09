<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\ValueObjects;

/** @internal */
final readonly class SolarPosition
{
    public function __construct(
        public float $declinationDeg,
        public float $equationOfTimeMinutes,
    ) {}
}
