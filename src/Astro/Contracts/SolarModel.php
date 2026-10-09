<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\Contracts;

/** @internal */
interface SolarModel
{
    public function declination(float $julianDay): float;

    public function equationOfTime(float $julianDay): float;
}
