<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Strategies;

use AlFalaq\AlFalak\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;

final class TabularCalendar implements HijriCalendar
{
    public function fromGregorian(\DateTimeImmutable|CivilDate $date): HijriDate
    {
        throw new \LogicException('not implemented: M4 tabular pending Appendix B');
    }

    public function monthLength(int $year, int $month): int
    {
        throw new \LogicException('not implemented: M4 tabular pending');
    }

    public function name(): string
    {
        return 'tabular';
    }
}
