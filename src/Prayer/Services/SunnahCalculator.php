<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Services;

use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalak\Prayer\ValueObjects\SunnahTimes;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Minutes;

final class SunnahCalculator
{
    public function __construct(
        private readonly PrayerSchedule $today,
        private readonly PrayerSchedule $tomorrow,
    ) {}

    public static function fromCalculator(
        PrayerTimesCalculator $calculator,
        Coordinates $coordinates,
        CivilDate $date,
        CalculationParameters $parameters,
    ): self {
        $today = $calculator->calculate($coordinates, $date, $parameters);
        $tomorrow = $calculator->calculate($coordinates, $date->plusDays(1), $parameters);

        return new self($today, $tomorrow);
    }

    public function calculate(): SunnahTimes
    {
        $maghrib = $this->today->maghrib;
        $fajr = $this->tomorrow->fajr;

        if ($fajr <= $maghrib) {
            throw new \AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException('Degenerate Sunnah night: fajr must be after maghrib.');
        }

        $nightSeconds = $fajr->getTimestamp() - $maghrib->getTimestamp();

        $at = static function (float $fraction) use ($maghrib, $nightSeconds): \DateTimeImmutable {
            $moved = $maghrib->modify(sprintf('+%d seconds', (int) round($nightSeconds * $fraction)));

            if ($moved === false) {
                throw new \AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException('Unable to compute Sunnah fraction.');
            }

            return Minutes::roundedHalfUp($moved);
        };

        return new SunnahTimes($at(0.5), $at(1.0 / 3.0), $at(2.0 / 3.0), $fajr);
    }
}
