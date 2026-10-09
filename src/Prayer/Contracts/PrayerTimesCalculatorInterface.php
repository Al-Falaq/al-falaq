<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

interface PrayerTimesCalculatorInterface
{
    public function calculate(Coordinates $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule;
}
