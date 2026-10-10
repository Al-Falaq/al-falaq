<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\SeventhOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\TwilightAngleCorrection;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Night-portion fractions per high-latitude rule (pin night_portions).
 */
final class HighLatitudeCorrectionTest extends TestCase
{
    public function testMiddleOfNightHalves(): void
    {
        $portions = (new MiddleOfNightCorrection())->portions(
            Coordinates::fromDegrees(59.9139, 10.7522),
            CivilDate::fromYmd(2025, 1, 15),
            18.0,
            17.0,
        );

        self::assertSame(0.5, $portions->fajrPortion);
        self::assertSame(0.5, $portions->ishaPortion);
    }

    public function testSeventhOfNight(): void
    {
        $portions = (new SeventhOfNightCorrection())->portions(
            Coordinates::fromDegrees(59.9139, 10.7522),
            CivilDate::fromYmd(2025, 1, 15),
            18.0,
            17.0,
        );

        self::assertEqualsWithDelta(1.0 / 7.0, $portions->fajrPortion, 1e-12);
        self::assertEqualsWithDelta(1.0 / 7.0, $portions->ishaPortion, 1e-12);
    }

    public function testTwilightAngleDividesBySixty(): void
    {
        $portions = (new TwilightAngleCorrection())->portions(
            Coordinates::fromDegrees(59.9139, 10.7522),
            CivilDate::fromYmd(2025, 1, 15),
            18.0,
            17.0,
        );

        self::assertEqualsWithDelta(0.3, $portions->fajrPortion, 1e-12);
        self::assertEqualsWithDelta(17.0 / 60.0, $portions->ishaPortion, 1e-12);
    }

    public function testTwilightAngleRejectsIntervalIsha(): void
    {
        $this->expectException(ConfigurationException::class);
        (new TwilightAngleCorrection())->portions(
            Coordinates::fromDegrees(59.9139, 10.7522),
            CivilDate::fromYmd(2025, 1, 15),
            18.0,
            null,
        );
    }
}
