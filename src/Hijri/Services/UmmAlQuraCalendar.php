<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Services;

use AlFalaq\AlFalak\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;

final class UmmAlQuraCalendar implements HijriCalendar
{
    public function fromGregorian(\DateTimeImmutable|CivilDate $date): HijriDate
    {
        throw new \LogicException('not implemented: M4 UQ table pending UQ_PROVENANCE.md data');
    }

    public function monthLength(int $year, int $month): int
    {
        throw new \LogicException('not implemented: M4 UQ table pending');
    }

    public function name(): string
    {
        return 'umm-al-qura';
    }
}
