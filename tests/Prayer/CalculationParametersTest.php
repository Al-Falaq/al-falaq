<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleMaghrib;
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

    public function testWithersPreserveMethodAndBase(): void
    {
        $base = self::qatarBase();

        $changed = $base->withFajrAngle(19.5);
        self::assertSame(19.5, $changed->fajrAngle);
        self::assertSame(CalculationMethod::QATAR, $changed->method);
        self::assertSame(18.0, $base->fajrAngle);

        $moved = $base->withHighLatitudeRule(HighLatitudeRule::SEVENTH_OF_NIGHT);
        self::assertSame(HighLatitudeRule::SEVENTH_OF_NIGHT, $moved->highLatitudeRule);
    }

    public function testCreateRejectsZeroIntervalIsha(): void
    {
        $this->expectException(ValidationException::class);

        CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new IntervalIsha(0),
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

    public function testCreateRejectsNegativeMaghribMinutes(): void
    {
        $this->expectException(ValidationException::class);

        CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new AngleIsha(17.0),
            new MinutesAfterSunsetMaghrib(-1),
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

    public function testCreateRejectsBoolElevation(): void
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
            15,
            28,
            true,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateRejectsInfiniteElevation(): void
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
            15,
            28,
            INF,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateRejectsExcessiveElevation(): void
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
            15,
            28,
            9000,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateRejectsBoolDhuhrOffset(): void
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
            true,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateRejectsExcessiveDhuhrOffset(): void
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
            6,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateRejectsNonFiniteFajr(): void
    {
        $this->expectException(ValidationException::class);

        CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            NAN,
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

    public function testWithDhuhaOffsetRejectsBelowIshraq(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withDhuhaOffset(10);
    }

    public function testWithMadhabSwapsSchoolOnly(): void
    {
        $base = self::qatarBase();

        $changed = $base->withMadhab(Madhab::HANAFI);

        self::assertSame(Madhab::HANAFI, $changed->madhab);
        self::assertSame(Madhab::SHAFI, $base->madhab);
        self::assertSame($base->fajrAngle, $changed->fajrAngle);
        self::assertSame($base->method, $changed->method);
    }

    public function testWithElevationMReplacesElevationOnly(): void
    {
        $base = self::qatarBase();

        $changed = $base->withElevationM(100.5);

        self::assertSame(100.5, $changed->elevationM);
        self::assertSame(0.0, $base->elevationM);
        self::assertSame($base->madhab, $changed->madhab);
    }

    public function testWithElevationMRejectsBool(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withElevationM(true);
    }

    public function testWithIshaRuleAcceptsAngleAndInterval(): void
    {
        $base = self::qatarBase();

        $angled = $base->withIshaRule(new AngleIsha(17.0));
        self::assertSame(17.0, $angled->ishaRule->angle());
        self::assertSame($base->fajrAngle, $angled->fajrAngle);

        $interval = $base->withIshaRule(new IntervalIsha(90));
        self::assertSame(90, $interval->ishaRule->minutesAfterMaghrib());
    }

    public function testWithIshaRuleRejectsOutOfRangeAngle(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withIshaRule(new AngleIsha(31.0));
    }

    public function testWithMaghribRuleAcceptsAngleAndMinutes(): void
    {
        $base = self::qatarBase();

        $angled = $base->withMaghribRule(new AngleMaghrib(3.0));
        self::assertSame(3.0, $angled->maghribRule->angle());
        self::assertSame($base->fajrAngle, $angled->fajrAngle);

        $minutes = $base->withMaghribRule(new MinutesAfterSunsetMaghrib(5));
        self::assertSame(5, $minutes->maghribRule->minutesAfterSunset());
        self::assertNull($minutes->maghribRule->angle());
    }

    public function testWithMaghribRuleRejectsNegativeMinutes(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withMaghribRule(new MinutesAfterSunsetMaghrib(-1));
    }

    public function testWithImsakOffsetReplacesOffsetOnly(): void
    {
        $base = self::qatarBase();

        $changed = $base->withImsakOffset(5);

        self::assertSame(5, $changed->imsakOffsetMinutes);
        self::assertSame(10, $base->imsakOffsetMinutes);
        self::assertSame($base->ishraqOffsetMinutes, $changed->ishraqOffsetMinutes);
    }

    public function testWithImsakOffsetRejectsBool(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withImsakOffset(false);
    }

    public function testWithIshraqOffsetReplacesOffsetOnly(): void
    {
        $base = self::qatarBase();

        $changed = $base->withIshraqOffset(10);

        self::assertSame(10, $changed->ishraqOffsetMinutes);
        self::assertSame(15, $base->ishraqOffsetMinutes);
        self::assertSame($base->dhuhaOffsetMinutes, $changed->dhuhaOffsetMinutes);
    }

    public function testWithIshraqOffsetRejectsDhuhaBelowNewValue(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withIshraqOffset(30);
    }

    public function testWithDhuhaOffsetReplacesOffsetOnly(): void
    {
        $base = self::qatarBase();

        $changed = $base->withDhuhaOffset(30);

        self::assertSame(30, $changed->dhuhaOffsetMinutes);
        self::assertSame(28, $base->dhuhaOffsetMinutes);
        self::assertSame($base->ishraqOffsetMinutes, $changed->ishraqOffsetMinutes);
    }

    public function testWithDhuhrOffsetReplacesOffsetOnly(): void
    {
        $base = self::qatarBase();

        $changed = $base->withDhuhrOffset(2);

        self::assertSame(2, $changed->dhuhrOffsetMinutes);
        self::assertSame(0, $base->dhuhrOffsetMinutes);
        self::assertSame($base->imsakOffsetMinutes, $changed->imsakOffsetMinutes);
    }

    public function testWithDhuhrOffsetRejectsExcessive(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withDhuhrOffset(6);
    }

    public function testWithMethodAdjustmentsReplacesLayerOnly(): void
    {
        $base = self::qatarBase();
        $layer = PrayerAdjustments::empty()->with(Prayer::FAJR, -2);

        $changed = $base->withMethodAdjustments($layer);

        self::assertSame(-2, $changed->methodAdjustments->for(Prayer::FAJR));
        self::assertSame(0, $base->methodAdjustments->for(Prayer::FAJR));
        self::assertSame($base->madhab, $changed->madhab);
    }

    public function testWithAdjustmentsReplacesCallerLayerOnly(): void
    {
        $base = self::qatarBase();
        $layer = PrayerAdjustments::empty()->with(Prayer::ISHA, 3);

        $changed = $base->withAdjustments($layer);

        self::assertSame(3, $changed->adjustments->for(Prayer::ISHA));
        self::assertSame(0, $base->adjustments->for(Prayer::ISHA));
    }

    public function testWithRamadanTogglesFlagOnly(): void
    {
        $base = self::qatarBase();

        $changed = $base->withRamadan(true);

        self::assertTrue($changed->isRamadan);
        self::assertFalse($base->isRamadan);
        self::assertSame($base->method, $changed->method);
    }

    public function testWithPolarCircleRuleSwapsRuleOnly(): void
    {
        $base = self::qatarBase();

        $changed = $base->withPolarCircleRule(PolarCircleRule::MAKKAH);

        self::assertSame(PolarCircleRule::MAKKAH, $changed->polarCircleRule);
        self::assertSame(PolarCircleRule::NEAREST_LATITUDE, $base->polarCircleRule);
    }

    public function testAdjustmentsRejectOutOfRangeMinutes(): void
    {
        $this->expectException(ValidationException::class);
        PrayerAdjustments::empty()->with(Prayer::FAJR, 61);
    }

    public function testAdjustmentsCombineAddOverlappingKeys(): void
    {
        $method = PrayerAdjustments::empty()->with(Prayer::DHUHR, 1)->with(Prayer::FAJR, -2);
        $caller = PrayerAdjustments::empty()->with(Prayer::DHUHR, 2)->with(Prayer::ISHA, 3);

        $merged = $method->combinedWith($caller);

        self::assertSame(3, $merged->for(Prayer::DHUHR));
        self::assertSame(-2, $merged->for(Prayer::FAJR));
        self::assertSame(3, $merged->for(Prayer::ISHA));
        self::assertSame(1, $method->for(Prayer::DHUHR));
    }

    public function testWithIshaRuleRejectsZeroInterval(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withIshaRule(new IntervalIsha(0));
    }
}
