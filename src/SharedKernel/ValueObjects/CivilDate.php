<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * A validated Gregorian civil date (years 1900–2100, the golden-tested range).
 */
final readonly class CivilDate
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    /**
     * Build from year/month/day, checked with checkdate().
     *
     * @param int $year 1900–2100 inclusive.
     * @param int $month 1–12.
     * @param int $day 1–31, valid for the month (leap years handled).
     * @return self Validated date.
     */
    public static function fromYmd(int $year, int $month, int $day): self
    {
        if ($year < 1900 || $year > 2100) {
            throw new ValidationException(sprintf('Invalid year %d: supported range is 1900–2100.', $year));
        }

        if (! checkdate($month, $day, $year)) {
            throw new ValidationException(sprintf('Invalid civil date %04d-%02d-%02d.', $year, $month, $day));
        }

        return new self($year, $month, $day);
    }

    /**
     * Extract the calendar date from any zoned instant (time discarded).
     *
     * @param \DateTimeImmutable $dateTime Source instant; only Y-M-D is read.
     * @return self The civil date in the instant's own zone.
     */
    public static function fromDateTime(\DateTimeImmutable $dateTime): self
    {
        return self::fromYmd(
            (int) $dateTime->format('Y'),
            (int) $dateTime->format('n'),
            (int) $dateTime->format('j'),
        );
    }

    /**
     * Midnight UTC starting this date (the canonical math anchor).
     *
     * @return \DateTimeImmutable UTC midnight.
     */
    public function toUtcMidnight(): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            sprintf('%d-%d-%d', $this->year, $this->month, $this->day),
            new \DateTimeZone('UTC'),
        );

        if ($date === false) {
            throw new ValidationException('Unable to build UTC midnight for civil date.');
        }

        return $date;
    }

    /**
     * Shift by whole days, positive or negative (month/year rollover handled).
     *
     * @param int $days Signed offset; plusDays(1)->plusDays(-1) round-trips.
     * @return self New date; this instance is unchanged.
     */
    public function plusDays(int $days): self
    {
        $moved = $this->toUtcMidnight()->modify(sprintf('%+d days', $days));

        if ($moved === false) {
            throw new ValidationException('Unable to add days to civil date.');
        }

        return self::fromDateTime($moved);
    }

    /**
     * Total ordering by (year, month, day).
     *
     * @param self $other Date to compare against.
     * @return bool True when all three fields match.
     */
    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month && $this->day === $other->day;
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

    /**
     * Zero-padded YYYY-MM-DD (stable key for timetables and goldens).
     *
     * @return string e.g. '2024-02-29'.
     */
    public function isoformat(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }
}
