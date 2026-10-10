<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Traits;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Coerce Coordinates|[lat, lon] inputs at service boundaries (single place).
 */
trait NormalizesCoordinatesInput
{
    /**
     * @param Coordinates|array{0: mixed, 1: mixed} $input
     */
    private static function normalizeCoordinates(Coordinates|array $input): Coordinates
    {
        return $input instanceof Coordinates ? $input : Coordinates::fromArray($input);
    }
}
