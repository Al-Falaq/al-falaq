<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Seasonal twilight offsets transcribed from pin Twilight.py.
 *
 * Vectors are oracle second-offsets, verified by executing the pin directly
 * (season_adjusted_morning/evening_twilight minus/plus the base instant).
 */
final class SeasonAdjustedTwilightTest extends TestCase
{
    public function testSeasonalSecondsMatchPin(): void
    {
        $twilight = new SeasonAdjustedTwilight();

        self::assertSame(['morning' => 6207, 'evening' => 5750], $twilight->seasonalSeconds(15, 2025, 59.9139));
        self::assertSame(['morning' => 7622, 'evening' => 4880], $twilight->seasonalSeconds(172, 2025, 59.9139));
        self::assertSame(['morning' => 5969, 'evening' => 4419], $twilight->seasonalSeconds(15, 2025, -33.8688));
        self::assertSame(['morning' => 6067, 'evening' => 5648], $twilight->seasonalSeconds(15, 2025, 55.0));
    }

    public function testSeasonalSecondsRejectsBadInput(): void
    {
        $this->expectException(ValidationException::class);
        (new SeasonAdjustedTwilight())->seasonalSeconds(0, 2025, 59.9139);
    }

    public function testSeasonalSecondsRejectsOutOfRangeYear(): void
    {
        $this->expectException(ValidationException::class);
        (new SeasonAdjustedTwilight())->seasonalSeconds(15, 1899, 59.9139);
    }

    public function testSeasonalSecondsRejectsNonFiniteLatitude(): void
    {
        $this->expectException(ValidationException::class);
        (new SeasonAdjustedTwilight())->seasonalSeconds(15, 2025, NAN);
    }

    public function testSeasonalSecondsWrapsPastYearEnd(): void
    {
        // Day 360 in the northern hemisphere: dayOfYear + 10 overflows the
        // 365-day year, so days-since-solstice wraps into early January.
        $offsets = (new SeasonAdjustedTwilight())->seasonalSeconds(360, 2025, 10.0);

        self::assertSame(['morning' => 4807, 'evening' => 4765], $offsets);
    }

    public function testSeasonalSecondsAcceptsDayOfYearBoundaries(): void
    {
        // The 1–366 guard is inclusive on both ends (mutation gate).
        $twilight = new SeasonAdjustedTwilight();

        self::assertSame(['morning' => 4800, 'evening' => 4748], $twilight->seasonalSeconds(1, 2025, 10.0));
        self::assertSame(['morning' => 4800, 'evening' => 4748], $twilight->seasonalSeconds(366, 2025, 10.0));
    }

    public function testSeasonalSecondsAcceptsYearBoundaries(): void
    {
        // The 1900–2100 guard is inclusive on both ends (mutation gate).
        $twilight = new SeasonAdjustedTwilight();

        self::assertSame(['morning' => 4785, 'evening' => 4709], $twilight->seasonalSeconds(15, 1900, 10.0));
        self::assertSame(['morning' => 4785, 'evening' => 4709], $twilight->seasonalSeconds(15, 2100, 10.0));
    }

    public function testSeasonalSecondsLeapSouthernHemisphere(): void
    {
        // Leap years shift the southern solstice offset (173, not 172) and
        // the leap probe is Feb 29 (never Feb 30): all three constants pinned.
        $offsets = (new SeasonAdjustedTwilight())->seasonalSeconds(1, 2024, -10.0);

        self::assertSame(['morning' => 4985, 'evening' => 4527], $offsets);
    }

    public function testSeasonalSecondsLeapNorthernYearLength(): void
    {
        // Leap years wrap the northern branch at 366, common years at 365.
        $twilight = new SeasonAdjustedTwilight();

        self::assertSame(['morning' => 4813, 'evening' => 4779], $twilight->seasonalSeconds(356, 2024, 10.0));
        self::assertSame(['morning' => 4811, 'evening' => 4776], $twilight->seasonalSeconds(355, 2024, 10.0));
    }

    public function testSeasonalSecondsEquatorUsesNorthernBranch(): void
    {
        // Latitude exactly 0.0 takes the northern (dayOfYear + 10) branch.
        $offsets = (new SeasonAdjustedTwilight())->seasonalSeconds(15, 2025, 0.0);

        self::assertSame(['morning' => 4500, 'evening' => 4500], $offsets);
    }

    public function testSeasonalSecondsNorthernWrapIsInclusive(): void
    {
        // dayOfYear + 10 == daysInYear wraps (>=, not >).
        $offsets = (new SeasonAdjustedTwilight())->seasonalSeconds(355, 2025, 10.0);

        self::assertSame(['morning' => 4813, 'evening' => 4779], $offsets);
    }

    public function testSeasonalSecondsSouthernZeroStaysPut(): void
    {
        // A southern days-since-solstice of exactly 0 is not rewrapped (<, not <=).
        $offsets = (new SeasonAdjustedTwilight())->seasonalSeconds(172, 2025, -10.0);

        self::assertSame(['morning' => 4813, 'evening' => 4779], $offsets);
    }

    public function testSeasonalSecondsMidSegmentSlopes(): void
    {
        // Interior segment offsets (dss - 91) and (dss - 229), not off by one.
        $twilight = new SeasonAdjustedTwilight();

        self::assertSame(['morning' => 4715, 'evening' => 4520], $twilight->seasonalSeconds(82, 2025, 10.0));
        self::assertSame(['morning' => 4854, 'evening' => 4402], $twilight->seasonalSeconds(220, 2025, 10.0));
    }
}
