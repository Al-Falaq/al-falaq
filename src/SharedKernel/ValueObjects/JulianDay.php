<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

/**
 * Julian Day number (astronomical day count; 2000-01-01 00:00 UTC = 2451544.5).
 */
final readonly class JulianDay
{
    private function __construct(public float $day) {}

    /**
     * Convert a civil date via the Fliegel–Van Flandern algorithm.
     *
     * @param CivilDate $date Gregorian date to convert.
     * @return self Day number at 00:00 UTC.
     */
    public static function fromCivilDate(CivilDate $date): self
    {
        $y = $date->year;
        $m = $date->month;
        $d = $date->day;

        if ($m <= 2) {
            $y -= 1;
            $m += 12;
        }

        $a = (int) floor($y / 100.0);
        $b = 2 - $a + (int) floor($a / 4.0);

        $jd = floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $d + $b - 1524.5;

        return new self($jd);
    }

    /**
     * Unwrap the raw day number for astronomy internals.
     *
     * @return float Julian Day as float.
     */
    public function toFloat(): float
    {
        return $this->day;
    }
}
