<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

final readonly class JulianDay
{
    private function __construct(public float $day) {}

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

    public function toFloat(): float
    {
        return $this->day;
    }
}
