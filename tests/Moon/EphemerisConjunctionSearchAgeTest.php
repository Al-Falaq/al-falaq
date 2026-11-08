<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Services\EphemerisConjunctionSearch;
use PHPUnit\Framework\TestCase;

/**
 * Moon-age search against real ephemeris samples: the conjunction-at-sunset
 * floor path and the previous-lunation path.
 *
 * The 30-day exhaustive fallback is pin-verbatim defensive code: a 29.53-day
 * synodic month always puts an interior ARCL minimum inside the 721-sample
 * window, so no real Julian day reaches it.
 */
final class EphemerisConjunctionSearchAgeTest extends TestCase
{
    public function testConjunctionAtSunsetReportsFloor(): void
    {
        // 2460763.951356 is the minute-scan ARCL minimum of the 2025-03-29
        // conjunction (the solar-eclipse new moon). The sunset sample is the
        // window minimum, so the forward-slope guard reports the 0.1-day floor
        // instead of the previous lunation (~29.4 d).
        $search = EphemerisConjunctionSearch::default();

        self::assertSame(0.1, $search->ageDays(2460763.951356));
        self::assertSame(0.1, $search->ageDays(2460763.973662));
    }

    public function testPreConjunctionFindsPreviousLunation(): void
    {
        // One day earlier the Moon is still waning toward the future
        // conjunction: the forward-slope guard must not claim the floor, and
        // the search returns the previous lunation's minimum.
        $search = EphemerisConjunctionSearch::default();

        self::assertEqualsWithDelta(28.416666666666927, $search->ageDays(2460763.951356 - 1.0), 1e-9);
    }

    public function testAgeStaysWithinSearchWindowAcrossTheYear(): void
    {
        $search = EphemerisConjunctionSearch::default();

        for ($jd = 2460000.0; $jd <= 2461000.0; $jd += 100.0) {
            $age = $search->ageDays($jd);

            self::assertTrue($age >= 0.1);
            self::assertTrue($age <= 30.0);
            self::assertTrue(is_finite($age));
        }
    }
}
