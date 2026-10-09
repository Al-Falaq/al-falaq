<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Traits;

use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

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
