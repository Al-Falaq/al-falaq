<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class MakkahHandler implements PolarCircleHandler
{
    public function resolve(Coordinates $coordinates): Coordinates
    {
        return Coordinates::fromDegrees(21.4225, 39.8262);
    }
}
