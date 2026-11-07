<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Hijri;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Exceptions\HijriDataException;
use AlFalaq\AlFalaq\Hijri\Traits\LooksUpMonthStarts;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;
use AlFalaq\AlFalaq\Hijri\ValueObjects\HijriDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use PHPUnit\Framework\TestCase;

/**
 * Table-lookup behavior: conversion, indexing and malformed generated data.
 */
final class LooksUpMonthStartsTest extends TestCase
{
    /**
     * Build a fixture calendar over the supplied month starts.
     *
     * @param non-empty-list<string> $starts Month starts plus sentinel.
     * @return HijriCalendar Fixture calendar.
     */
    private function calendar(array $starts): HijriCalendar
    {
        return new class ($starts) implements HijriCalendar {
            use LooksUpMonthStarts;
            use ShiftsHijriDays;

            /**
             * Bind the fixture table.
             *
             * @param non-empty-list<string> $starts Month starts plus sentinel.
             */
            public function __construct(private readonly array $starts) {}

            /**
             * Fixture table for the trait.
             *
             * @return array{name: string, range: string, firstYear: int, firstMonth: int, starts: non-empty-list<string>}
             */
            protected function monthStartTable(): array
            {
                return [
                    'name' => 'fixture',
                    'range' => '1445–1446 AH',
                    'firstYear' => 1445,
                    'firstMonth' => 1,
                    'starts' => $this->starts,
                ];
            }

            /**
             * Fixture key.
             *
             * @return string Always 'fixture'.
             */
            public function name(): string
            {
                return 'fixture';
            }
        };
    }

    /**
     * A valid table converts in both directions and indexes later months.
     *
     * @return void
     */
    public function testValidTableConvertsBothWays(): void
    {
        $calendar = $this->calendar(['2023-07-19', '2023-08-18', '2023-09-16']);

        self::assertSame('1445-01-01', $calendar->fromGregorian(CivilDate::fromYmd(2023, 7, 19))->isoformat());
        self::assertSame('1445-01-30', $calendar->fromGregorian(CivilDate::fromYmd(2023, 8, 17))->isoformat());
        self::assertSame('2023-08-18', $calendar->toGregorian(new HijriDate(1445, 2, 1))->isoformat());
    }

    /**
     * A stored impossible day must not normalize into a different month start.
     *
     * @return void
     */
    public function testImpossibleStoredDayRaisesDataException(): void
    {
        $calendar = $this->calendar(['2023-02-30', '2023-03-30']);

        try {
            $calendar->fromGregorian(CivilDate::fromYmd(2023, 3, 1));
            self::fail('expected HijriDataException');
        } catch (HijriDataException $exception) {
            self::assertStringContainsString('2023-02-30', $exception->getMessage());
        }
    }

    /**
     * A stored day the parser cannot read at all raises the same data error.
     *
     * @return void
     */
    public function testUnparseableStoredDayRaisesDataException(): void
    {
        $calendar = $this->calendar(['2023-07-19', 'not-a-date']);

        $this->expectException(HijriDataException::class);
        $calendar->toGregorian(new HijriDate(1445, 1, 1));
    }
}
