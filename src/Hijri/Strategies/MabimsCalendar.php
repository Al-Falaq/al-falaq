<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\Strategies;

use AlFalaq\AlFalak\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalak\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;

/**
 * MABIMS (MY/ID/SG/BN) Neo-criteria calendar; new countries = new class (M4).
 */
final class MabimsCalendar implements HijriCalendar
{
    /**
     * Convert via MABIMS country rules (M4).
     *
     * @param \DateTimeImmutable|CivilDate $date Source; zone-aware, never naive.
     * @return HijriDate Converted date.
     */
    public function fromGregorian(\DateTimeImmutable|CivilDate $date): HijriDate
    {
        throw new \LogicException('not implemented: M4 MABIMS pending');
    }

    /**
     * MABIMS month length (M4).
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int Month length in days.
     */
    public function monthLength(int $year, int $month): int
    {
        throw new \LogicException('not implemented: M4 MABIMS pending');
    }

    /**
     * Stable calendar key.
     *
     * @return string Always 'mabims'.
     */
    public function name(): string
    {
        return 'mabims';
    }
}
