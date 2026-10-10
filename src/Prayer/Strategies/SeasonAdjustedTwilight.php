<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\TwilightModel;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Moon-Sighting-Committee seasonal twilight offsets (pin Twilight.py, M1).
 *
 * Polynomial second offsets from sunrise/sunset; the calculator applies
 * them as safe caps on every MSC row (seventh-of-night temps at |lat| >= 55).
 */
final class SeasonAdjustedTwilight implements TwilightModel
{
    /**
     * Seasonal second offsets for a day-of-year, year and latitude.
     *
     * Transcribed from pin season_adjusted_morning/evening_twilight: the pin
     * applies `timedelta(seconds=±int(round(adjustment * 60.0)))`, so the
     * offsets keep full second precision (a minutes collapse loses up to 29 s
     * and flips minute rounding on MSC rows — verified against the pin).
     *
     * @param int $dayOfYear 1–366.
     * @param int $year Gregorian year, 1900–2100 (leap-year solstice shift).
     * @param float $latitude Observer latitude in degrees.
     * @return array{morning: int, evening: int} Seconds before sunrise / after sunset.
     */
    public function seasonalSeconds(int $dayOfYear, int $year, float $latitude): array
    {
        if ($dayOfYear < 1 || $dayOfYear > 366) {
            throw new ValidationException(sprintf('Invalid day of year %d: supported range is 1–366.', $dayOfYear));
        }

        if ($year < 1900 || $year > 2100) {
            throw new ValidationException(sprintf('Invalid year %d: supported range is 1900–2100.', $year));
        }

        if (! is_finite($latitude)) {
            throw new ValidationException('Invalid latitude: must be finite.');
        }

        $dss = $this->daysSinceSolstice($dayOfYear, $year, $latitude);
        $absLat = abs($latitude);

        $morning = $this->interpolate(
            $dss,
            75.0 + (28.65 / 55.0) * $absLat,
            75.0 + (19.44 / 55.0) * $absLat,
            75.0 + (32.74 / 55.0) * $absLat,
            75.0 + (48.10 / 55.0) * $absLat,
        );

        $evening = $this->interpolate(
            $dss,
            75.0 + (25.60 / 55.0) * $absLat,
            75.0 + (2.050 / 55.0) * $absLat,
            75.0 - (9.210 / 55.0) * $absLat,
            75.0 + (6.140 / 55.0) * $absLat,
        );

        return ['morning' => (int) round($morning * 60.0), 'evening' => (int) round($evening * 60.0)];
    }

    /**
     * Days since the December solstice (pin days_since_solstice).
     *
     * @param int $dayOfYear 1–366.
     * @param int $year Gregorian year.
     * @param float $latitude Observer latitude in degrees.
     * @return int Days since solstice.
     */
    private function daysSinceSolstice(int $dayOfYear, int $year, float $latitude): int
    {
        $leap = checkdate(2, 29, $year);
        $southernOffset = $leap ? 173 : 172;
        $daysInYear = $leap ? 366 : 365;

        if ($latitude >= 0.0) {
            $dss = $dayOfYear + 10;

            if ($dss >= $daysInYear) {
                $dss -= $daysInYear;
            }
        } else {
            $dss = $dayOfYear - $southernOffset;

            if ($dss < 0) {
                $dss += $daysInYear;
            }
        }

        return $dss;
    }

    /**
     * Piecewise-linear seasonal curve (pin inline segments).
     *
     * @param int $dss Days since solstice.
     * @param float $a Solstice value.
     * @param float $b Early-season value.
     * @param float $c Mid-season value.
     * @param float $d Peak-season value.
     * @return float Interpolated adjustment in minutes.
     */
    private function interpolate(int $dss, float $a, float $b, float $c, float $d): float
    {
        if ($dss < 91) {
            return $a + ($b - $a) / 91.0 * $dss;
        }

        if ($dss < 137) {
            return $b + ($c - $b) / 46.0 * ($dss - 91);
        }

        if ($dss < 183) {
            return $c + ($d - $c) / 46.0 * ($dss - 137);
        }

        if ($dss < 229) {
            return $d + ($c - $d) / 46.0 * ($dss - 183);
        }

        if ($dss < 275) {
            return $c + ($b - $c) / 46.0 * ($dss - 229);
        }

        return $b + ($a - $b) / 91.0 * ($dss - 275);
    }
}
