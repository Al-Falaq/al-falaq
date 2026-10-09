<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class NearestLatitudeHandler implements PolarCircleHandler
{
    public function resolve(Coordinates $coordinates): Coordinates
    {
        throw new \LogicException('not implemented: M1 polar pending');
    }
}
