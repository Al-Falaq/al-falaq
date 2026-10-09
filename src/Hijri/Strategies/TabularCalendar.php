<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Strategies;

use AlFalaq\AlFalak\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;

/**
 * Tabular (Kuwaiti algorithm) calendar: 30-year leap cycle, no table (M4).
 */
final class TabularCalendar implements HijriCalendar
{
    /**
     * Convert via the arithmetic 30-year cycle (M4).
     *
     * @param \DateTimeImmutable|CivilDate $date Source; zone-aware, never naive.
     * @return HijriDate Converted date.
     */
    public function fromGregorian(\DateTimeImmutable|CivilDate $date): HijriDate
    {
        throw new \LogicException('not implemented: M4 tabular pending Appendix B');
    }

    /**
     * Tabular month length from the leap cycle (M4).
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int Month length in days.
     */
    public function monthLength(int $year, int $month): int
    {
        throw new \LogicException('not implemented: M4 tabular pending');
    }

    /**
     * Stable calendar key.
     *
     * @return string Always 'tabular'.
     */
    public function name(): string
    {
        return 'tabular';
    }
}
