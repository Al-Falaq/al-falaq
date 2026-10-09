<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

interface PolarCircleHandler
{
    public function resolve(Coordinates $coordinates): Coordinates;
}
