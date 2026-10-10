<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

/**
 * Umm al-Qura calendar: shipped 1356–1500 AH table, binary search (M4).
 *
 * Range-guarded: outside the table throws HijriOutOfRangeException.
 */
final class UmmAlQuraCalendar implements HijriCalendar
{
    /**
     * Convert via table lookup (M4).
     *
     * @param \DateTimeImmutable|CivilDate $date Source; zone-aware, never naive.
     * @return HijriDate Converted date.
     */
    public function fromGregorian(\DateTimeImmutable|CivilDate $date): HijriDate
    {
        throw new \LogicException('not implemented: M4 UQ table pending UQ_PROVENANCE.md data');
    }

    /**
     * Tabled month length (M4).
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int Month length in days.
     */
    public function monthLength(int $year, int $month): int
    {
        throw new \LogicException('not implemented: M4 UQ table pending');
    }

    /**
     * Stable calendar key.
     *
     * @return string Always 'umm-al-qura'.
     */
    public function name(): string
    {
        return 'umm-al-qura';
    }
}
