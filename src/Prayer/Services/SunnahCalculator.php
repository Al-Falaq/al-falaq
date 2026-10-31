<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\Prayer\ValueObjects\SunnahTimes;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Minutes;

/**
 * Sunnah night markers (middle of night, thirds) from two UTC schedules (M2).
 *
 * Night runs today.maghrib → tomorrow.fajr; all math in UTC, DST-safe.
 */
final class SunnahCalculator
{
    /**
     * Bind the two schedules bracketing one night (both UTC).
     *
     * @param PrayerSchedule $today Schedule holding tonight's maghrib.
     * @param PrayerSchedule $tomorrow Schedule holding tomorrow's fajr.
     */
    public function __construct(
        private readonly PrayerSchedule $today,
        private readonly PrayerSchedule $tomorrow,
    ) {}

    /**
     * Build from a calculator instead of precomputed schedules.
     *
     * @param PrayerTimesCalculatorInterface $calculator Single-day pipeline.
     * @param Coordinates $coordinates Observer location.
     * @param CivilDate $date Civil date of tonight's maghrib.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return self Calculator bound to tonight + tomorrow night.
     */
    public static function fromCalculator(
        PrayerTimesCalculatorInterface $calculator,
        Coordinates $coordinates,
        CivilDate $date,
        CalculationParameters $parameters,
    ): self {
        $today = $calculator->calculate($coordinates, $date, $parameters);
        $tomorrow = $calculator->calculate($coordinates, $date->plusDays(1), $parameters);

        return new self($today, $tomorrow);
    }

    /**
     * Compute middle of night, thirds, and tahajjud window (UTC).
     *
     * @return SunnahTimes Markers in UTC; display via forZone().
     */
    public function calculate(): SunnahTimes
    {
        $maghrib = $this->today->maghrib;
        $fajr = $this->tomorrow->fajr;

        if ($fajr <= $maghrib) {
            throw new ValidationException('Degenerate Sunnah night: fajr must be after maghrib.');
        }

        $nightSeconds = $fajr->getTimestamp() - $maghrib->getTimestamp();

        $at = static function (float $fraction) use ($maghrib, $nightSeconds): \DateTimeImmutable {
            $moved = $maghrib->modify(sprintf('+%d seconds', (int) round($nightSeconds * $fraction)));

            if ($moved === false) {
                throw new ValidationException('Unable to compute Sunnah fraction.');
            }

            return Minutes::roundedHalfUp($moved);
        };

        return new SunnahTimes($at(0.5), $at(1.0 / 3.0), $at(2.0 / 3.0), $fajr, $maghrib);
    }
}
