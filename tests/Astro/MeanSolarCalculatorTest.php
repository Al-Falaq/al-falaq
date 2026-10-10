<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Meeus Ch.25 solar position transcribed from pin a7baa8b.
 *
 * Vectors are oracle outputs of pin Astronomical.solar_declination /
 * equation_of_time (author-verified 2026-10-10).
 */
final class MeanSolarCalculatorTest extends TestCase
{
    public function testDeclinationAndEotMatchPin(): void
    {
        $calc = new MeanSolarCalculator();

        self::assertEqualsWithDelta(-23.03251608590199, $calc->declination(2451545.0), 1e-9);
        self::assertEqualsWithDelta(-3.3007602707561516, $calc->equationOfTime(2451545.0), 1e-9);
        self::assertEqualsWithDelta(-22.998077976633684, $calc->declination(2460676.5), 1e-9);
        self::assertEqualsWithDelta(-3.4502404616062683, $calc->equationOfTime(2460676.5), 1e-9);
        self::assertEqualsWithDelta(-19.862498983037842, $calc->declination(2461000.25), 1e-9);
        self::assertEqualsWithDelta(14.267116404509053, $calc->equationOfTime(2461000.25), 1e-9);

        $pos = $calc->position(2451545.0);
        self::assertEqualsWithDelta(-23.03251608590199, $pos->declinationDeg, 1e-9);
        self::assertEqualsWithDelta(-3.3007602707561516, $pos->equationOfTimeMinutes, 1e-9);
    }
}
