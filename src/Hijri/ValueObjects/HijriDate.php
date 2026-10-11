<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * A validated Hijri date (universal day bound 1..30; the calendar tightens it).
 *
 * Day arithmetic lives on the calendar (`HijriCalendar::shift()`) — a Hijri
 * date cannot know its own month length without a calendar.
 */
final readonly class HijriDate
{
    /**
     * Bind a validated Hijri triple.
     *
     * @param int $year Hijri year, >= 1 (Hijri years have no year zero).
     * @param int $month Hijri month 1–12.
     * @param int $day Day 1–30 (universal bound; month length enforced at conversion).
     */
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
        if ($year < 1) {
            throw new ValidationException(sprintf('Invalid Hijri year %d: Hijri years start at 1.', $year));
        }

        if ($month < 1 || $month > 12) {
            throw new ValidationException(sprintf('Invalid Hijri month %d.', $month));
        }

        if ($day < 1 || $day > 30) {
            throw new ValidationException(sprintf('Invalid Hijri day %d: universal bound is 1..30.', $day));
        }
    }

    /**
     * Zero-padded ISO 8601 rendering (YYYY-MM-DD).
     *
     * @return string Canonical date string.
     */
    public function isoformat(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    /**
     * Three-way comparison by year, then month, then day.
     *
     * @param HijriDate $other Date to compare against.
     * @return int Negative, zero or positive.
     */
    public function compareTo(HijriDate $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }

    /**
     * Value equality.
     *
     * @param HijriDate $other Date to compare against.
     * @return bool True when year, month and day all match.
     */
    public function equals(HijriDate $other): bool
    {
        return $this->compareTo($other) === 0;
    }
}
