<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Contracts;

use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;

interface HijriCalendar
{
    public function fromGregorian(\DateTimeImmutable|CivilDate $date): HijriDate;

    public function monthLength(int $year, int $month): int;

    public function name(): string;
}
