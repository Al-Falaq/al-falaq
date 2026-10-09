<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

/**
 * A validated Hijri date (month 1–12, day 1–30 universal bound).
 *
 * Exact month lengths are enforced by the calendar at conversion time.
 */
final readonly class HijriDate
{
    /**
     * Validated triple (month/day bounds enforced here).
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @param int $day Day 1–30 (calendar tightens to 29/30 on conversion).
     */
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
        if ($month < 1 || $month > 12) {
            throw new ValidationException(sprintf('Invalid Hijri month %d.', $month));
        }

        if ($day < 1 || $day > 30) {
            throw new ValidationException(sprintf('Invalid Hijri day %d: universal bound is 1..30.', $day));
        }
    }

    /**
     * Shift by whole days across month/year boundaries (M4).
     *
     * @param int $days Signed offset.
     * @return self New date; this instance is unchanged.
     */
    public function plusDays(int $days): self
    {
        throw new \LogicException('not implemented: M4 Hijri arithmetic pending calendar month lengths');
    }

    /**
     * Zero-padded YYYY-MM-DD (stable key for goldens and timetables).
     *
     * @return string e.g. '1446-09-01'.
     */
    public function isoformat(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    /**
     * Total ordering by (year, month, day).
     *
     * @param self $other Date to compare against.
     * @return bool True when all three fields match.
     */
    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /**
     * Three-way comparison for sorting and range checks.
     *
     * @param self $other Date to compare against.
     * @return int Negative, zero, or positive (spaceship on Y-M-D).
     */
    public function compareTo(self $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }
}
