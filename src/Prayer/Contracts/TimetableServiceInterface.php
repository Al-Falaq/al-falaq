<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

interface TimetableServiceInterface
{
    /** @return list<PrayerSchedule> */
    public function monthly(Coordinates $coordinates, int $year, int $month, CalculationParameters $parameters): array;
}
