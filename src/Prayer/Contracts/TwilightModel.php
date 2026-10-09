<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

interface TwilightModel
{
    /** @return array{morning: float, evening: float} */
    public function adjustedAngles(int $dayOfYear, float $latitude): array;
}
