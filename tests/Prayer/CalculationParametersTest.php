<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\IntervalIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Validated factory for prayer configuration (M1 freeze foundation).
 */
final class CalculationParametersTest extends TestCase
{
    private static function qatarBase(): CalculationParameters
    {
        return CalculationParameters::create(
            CalculationMethod::QATAR,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new IntervalIsha(90),
            new MinutesAfterSunsetMaghrib(0),
            0,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateBuildsMwlLikeParams(): void
    {
        $params = CalculationParameters::create(
            CalculationMethod::MUSLIM_WORLD_LEAGUE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new AngleIsha(17.0),
            new MinutesAfterSunsetMaghrib(0),
            0,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty()->with(Prayer::DHUHR, 1),
            PrayerAdjustments::empty(),
        );

        self::assertSame(CalculationMethod::MUSLIM_WORLD_LEAGUE, $params->method);
        self::assertSame(18.0, $params->fajrAngle);
        self::assertSame(1, $params->methodAdjustments->for(Prayer::DHUHR));
    }

    public function testQatarBaseBuildsIntervalIsha(): void
    {
        $params = self::qatarBase();

        self::assertSame(CalculationMethod::QATAR, $params->method);
        self::assertSame(90, $params->ishaRule->minutesAfterMaghrib());
        self::assertSame(0, $params->maghribRule->minutesAfterSunset());
    }

    public function testCreateRejectsOutOfRangeAngles(): void
    {
        $this->expectException(ValidationException::class);

        CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            31.0,
            new AngleIsha(17.0),
            new MinutesAfterSunsetMaghrib(0),
            0,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateRejectsDhuhaBelowIshraq(): void
    {
        $this->expectException(ValidationException::class);

        CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new AngleIsha(17.0),
            new MinutesAfterSunsetMaghrib(0),
            0,
            10,
            28,
            15,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }
}
