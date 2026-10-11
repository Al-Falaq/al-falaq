<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Traits;

use AlFalaq\AlFalaq\Hijri\Exceptions\HijriOutOfRangeException;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;

/**
 * Table-backed Hijri conversion shared by the observational calendars.
 *
 * The using class supplies one generated month-start table (plus its key and
 * range label); this trait owns the binary search, day indexing and month
 * lengths, so both observational calendars behave identically at every edge.
 *
 * Table layout: `starts[i]` is the first Gregorian day of month `i`, and the
 * final entry is a sentinel one month past the last supported month. Dates
 * before `starts[0]` or on/after the sentinel are out of span and raise.
 */
trait LooksUpMonthStarts
{
    /**
     * Generated table bundle for the using calendar.
     *
     * @return array{name: string, range: string, firstYear: int, firstMonth: int, starts: non-empty-list<string>}
     */
    abstract protected function monthStartTable(): array;

    /**
     * Convert a Gregorian day to its Hijri date by table lookup.
     *
     * @param CivilDate $date Gregorian day.
     * @return HijriDate Matching Hijri date.
     */
    public function fromGregorian(CivilDate $date): HijriDate
    {
        $table = $this->monthStartTable();
        $starts = $table['starts'];
        $iso = $date->isoformat();

        if ($iso < $starts[0] || $iso >= $starts[count($starts) - 1]) {
            throw HijriOutOfRangeException::forDate($table['name'], $iso, $table['range']);
        }

        $low = 0;
        $high = count($starts) - 2;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($iso >= $starts[$middle]) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        $offset = $table['firstMonth'] - 1 + $low;

        return new HijriDate(
            $table['firstYear'] + intdiv($offset, 12),
            $offset % 12 + 1,
            self::daysBetween($starts[$low], $iso) + 1,
        );
    }

    /**
     * Convert a Hijri date to the Gregorian day it starts on.
     *
     * @param HijriDate $date Hijri date; its month length is enforced here.
     * @return CivilDate Matching Gregorian day.
     */
    public function toGregorian(HijriDate $date): CivilDate
    {
        $table = $this->monthStartTable();
        $starts = $table['starts'];
        $index = $this->tableIndex($date->year, $date->month);
        $length = self::daysBetween($starts[$index], $starts[$index + 1]);

        if ($date->day > $length) {
            throw new ValidationException(sprintf(
                'Invalid Hijri day %d for %s %s: month has %d days.',
                $date->day,
                $table['name'],
                $date->isoformat(),
                $length,
            ));
        }

        return self::parseIso($starts[$index])->plusDays($date->day - 1);
    }

    /**
     * Number of days in one supported Hijri month.
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int 29 or 30.
     */
    public function monthLength(int $year, int $month): int
    {
        $table = $this->monthStartTable();

        if ($month < 1 || $month > 12) {
            throw new ValidationException(sprintf('%s month must be within [1, 12], got %d.', $table['name'], $month));
        }

        $index = $this->tableIndex($year, $month);

        return self::daysBetween($table['starts'][$index], $table['starts'][$index + 1]);
    }

    /**
     * Flat table index for one Hijri year-month, range-checked.
     *
     * @param int $year Hijri year.
     * @param int $month Hijri month 1–12.
     * @return int Zero-based index into the table's month starts.
     */
    private function tableIndex(int $year, int $month): int
    {
        $table = $this->monthStartTable();
        $index = ($year - $table['firstYear']) * 12 + ($month - $table['firstMonth']);

        if ($index < 0 || $index >= count($table['starts']) - 1) {
            throw HijriOutOfRangeException::forDate(
                $table['name'],
                sprintf('%04d-%02d', $year, $month),
                $table['range'],
            );
        }

        return $index;
    }

    /**
     * Whole-day difference between two stored ISO start days.
     *
     * @param string $fromIso Earlier day, YYYY-MM-DD.
     * @param string $toIso Later day, YYYY-MM-DD.
     * @return int Non-negative whole-day difference.
     */
    private static function daysBetween(string $fromIso, string $toIso): int
    {
        return (int) self::parseIso($fromIso)->toUtcMidnight()->diff(self::parseIso($toIso)->toUtcMidnight())->days;
    }

    /**
     * Parse one stored table day.
     *
     * @param string $iso Stored day, YYYY-MM-DD.
     * @return CivilDate Parsed Gregorian day.
     */
    private static function parseIso(string $iso): CivilDate
    {
        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso, new \DateTimeZone('UTC'));

        if ($dateTime === false) {
            throw new \LogicException(sprintf('Malformed generated month start %s.', $iso));
        }

        return CivilDate::fromDateTime($dateTime);
    }
}
