<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Services;

use AlFalaq\AlFalak\Prayer\Contracts\TimetableServiceInterface;
use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class TimetableService implements TimetableServiceInterface
{
    public function monthly(Coordinates $coordinates, int $year, int $month, CalculationParameters $parameters): array
    {
        throw new \LogicException('not implemented: M1 timetable pending');
    }
}
