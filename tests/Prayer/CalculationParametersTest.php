<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Contracts\IshaRule;
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
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
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

    public function testWithMaghribRuleRejectsOutOfRangeAngle(): void
    {
        // The maghrib angle re-validation in the wither must stay.
        $this->expectException(ValidationException::class);
        self::qatarBase()->withMaghribRule(new AngleMaghrib(31.0));
    }

    public function testWithMaghribRuleAcceptsZeroMinutes(): void
    {
        // Zero minutes after sunset is valid (mutation gate).
        $changed = self::qatarBase()->withMaghribRule(new MinutesAfterSunsetMaghrib(0));

        self::assertSame(0, $changed->maghribRule->minutesAfterSunset());
    }

    public function testWithIshraqOffsetAcceptsEqualToDhuha(): void
    {
        // Raising ishraq exactly onto dhuha is valid (mutation gate).
        $changed = self::qatarBase()->withIshraqOffset(28);

        self::assertSame(28, $changed->ishraqOffsetMinutes);
    }

    public function testWithDhuhrOffsetAcceptsBoundaries(): void
    {
        // The wither dhuhr range is [0, 5]: both ends construct.
        self::assertSame(5, self::qatarBase()->withDhuhrOffset(5)->dhuhrOffsetMinutes);
        self::assertSame(0, self::qatarBase()->withDhuhrOffset(0)->dhuhrOffsetMinutes);

        $this->expectException(ValidationException::class);
        self::qatarBase()->withDhuhrOffset(-1);
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
        $method = CalculationMethod::UMM_AL_QURA;
        $base = CalculationParameters::create(
            $method,
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

    public function testAdjustmentsCombineAcceptsAggregateBoundaries(): void
    {
        self::assertSame(
            -60,
            PrayerAdjustments::empty()->with(Prayer::FAJR, -30)
                ->combinedWith(PrayerAdjustments::empty()->with(Prayer::FAJR, -30))
                ->for(Prayer::FAJR),
        );
        self::assertSame(
            60,
            PrayerAdjustments::empty()->with(Prayer::FAJR, 30)
                ->combinedWith(PrayerAdjustments::empty()->with(Prayer::FAJR, 30))
                ->for(Prayer::FAJR),
        );
    }

    public function testAdjustmentsCombineRejectsAggregateOutsideRange(): void
    {
        foreach ([[-30, -31], [30, 31]] as [$first, $second]) {
            try {
                PrayerAdjustments::empty()->with(Prayer::FAJR, $first)
                    ->combinedWith(PrayerAdjustments::empty()->with(Prayer::FAJR, $second));

                self::fail(sprintf('combined adjustment %d must reject sum %d.', $first, $first + $second));
            } catch (ValidationException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testWithIshaRuleRejectsZeroInterval(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withIshaRule(new IntervalIsha(0));
    }

    public function testCreateRejectsRamadanOutsideUmmAlQura(): void
    {
        // Ramadan consistency fails fast at construction, never at calculate().
        $this->expectException(ConfigurationException::class);

        CalculationParameters::create(
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
            true,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testWithRamadanRejectsNonUmmAlQura(): void
    {
        $this->expectException(ConfigurationException::class);
        self::qatarBase()->withRamadan(true);
    }

    public function testWithRamadanAcceptsUmmAlQura(): void
    {
        $params = CalculationMethod::UMM_AL_QURA->defaultParameters()->withRamadan(true);

        self::assertTrue($params->isRamadan);
    }

    public function testCustomIshaRuleValidatedThroughInterface(): void
    {
        // Third-party adapters validate through IshaRule::validate(), never
        // through instanceof on shipped concretes (an angle-based custom with
        // a null interval used to be misreported as an invalid interval).
        $custom = new class implements IshaRule {
            public function minutesAfterMaghrib(): ?int
            {
                return null;
            }

            public function angle(): float
            {
                return 17.0;
            }

            public function validate(): void
            {
                if ($this->angle() < 0.0 || $this->angle() > 30.0) {
                    throw new ValidationException('Custom isha angle out of range.');
                }
            }
        };

        $params = self::qatarBase()->withIshaRule($custom);

        self::assertSame($custom, $params->ishaRule);

        $rejecting = new class implements IshaRule {
            public function minutesAfterMaghrib(): ?int
            {
                return null;
            }

            public function angle(): float
            {
                return 45.0;
            }

            public function validate(): void
            {
                throw new ValidationException('Custom isha angle out of range.');
            }
        };

        try {
            self::qatarBase()->withIshaRule($rejecting);
            self::fail('expected ValidationException for custom invalid isha rule.');
        } catch (ValidationException $e) {
            self::assertSame('Custom isha angle out of range.', $e->getMessage());
        }
    }

    public function testCreateAcceptsBoundaryAngles(): void
    {
        // validateAngle is inclusive on both ends of [0, 30] (mutation gate).
        foreach ([0.0, 30.0] as $angle) {
            $params = CalculationParameters::create(
                CalculationMethod::NONE,
                Madhab::SHAFI,
                HighLatitudeRule::MIDDLE_OF_NIGHT,
                PolarCircleRule::NEAREST_LATITUDE,
                $angle,
                new AngleIsha($angle),
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

            self::assertSame($angle, $params->fajrAngle);
            self::assertSame($angle, $params->ishaRule->angle());
        }
    }

    public function testCreateRejectsOutOfRangeIshaAngle(): void
    {
        // The isha angle re-validation in create() must stay (mutation gate).
        $this->expectException(ValidationException::class);

        CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new AngleIsha(31.0),
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

    public function testCreateRejectsOutOfRangeMaghribAngle(): void
    {
        // The maghrib angle re-validation in create() must stay (mutation gate).
        $this->expectException(ValidationException::class);

        CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new AngleIsha(17.0),
            new AngleMaghrib(31.0),
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

    public function testCreateAcceptsSingleMinuteIntervalIsha(): void
    {
        // Interval floor is >= 1, so exactly 1 minute is valid (mutation gate).
        $params = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new IntervalIsha(1),
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

        self::assertSame(1, $params->ishaRule->minutesAfterMaghrib());
    }

    public function testCreateRejectsNegativeDhuhrOffset(): void
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
            -1,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateAcceptsMaxDhuhrOffset(): void
    {
        // Dhuhr offset range is [0, 5]: both ends construct (mutation gate).
        $params = CalculationParameters::create(
            CalculationMethod::NONE,
            Madhab::SHAFI,
            HighLatitudeRule::MIDDLE_OF_NIGHT,
            PolarCircleRule::NEAREST_LATITUDE,
            18.0,
            new AngleIsha(17.0),
            new MinutesAfterSunsetMaghrib(0),
            5,
            10,
            15,
            28,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        self::assertSame(5, $params->dhuhrOffsetMinutes);
    }

    public function testCreateAcceptsDhuhaEqualToIshraq(): void
    {
        // dhuha >= ishraq: equality is valid (mutation gate).
        $params = CalculationParameters::create(
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
            15,
            0.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        self::assertSame(15, $params->dhuhaOffsetMinutes);
    }

    public function testCreateAcceptsIntElevation(): void
    {
        // Ints normalize to float (mutation gate: the cast is load-bearing).
        $params = CalculationParameters::create(
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
            5,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        self::assertSame(5.0, $params->elevationM);
    }

    public function testCreateRejectsStringElevation(): void
    {
        // Strings are neither int nor float (mutation gate).
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
            '5',
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }

    public function testCreateAcceptsMaxElevation(): void
    {
        // Elevation cap is inclusive at 8848 (mutation gate).
        $params = CalculationParameters::create(
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
            8848.0,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );

        self::assertSame(8848.0, $params->elevationM);
    }

    public function testWithIshaRuleAcceptsSingleMinuteInterval(): void
    {
        $changed = self::qatarBase()->withIshaRule(new IntervalIsha(1));

        self::assertSame(1, $changed->ishaRule->minutesAfterMaghrib());
    }

    public function testWithDhuhaOffsetAcceptsEqualToIshraq(): void
    {
        $changed = self::qatarBase()->withDhuhaOffset(15);

        self::assertSame(15, $changed->dhuhaOffsetMinutes);
    }

    public function testWithImsakOffsetEnforcesZeroToSixty(): void
    {
        // Wither guard range is [0, 60]: both ends construct (mutation gate).
        self::assertSame(60, self::qatarBase()->withImsakOffset(60)->imsakOffsetMinutes);
        self::assertSame(0, self::qatarBase()->withImsakOffset(0)->imsakOffsetMinutes);

        foreach ([61, -1] as $bad) {
            try {
                self::qatarBase()->withImsakOffset($bad);

                self::fail(sprintf('withImsakOffset(%d) must throw ValidationException.', $bad));
            } catch (ValidationException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testWithImsakOffsetRejectsString(): void
    {
        $this->expectException(ValidationException::class);
        self::qatarBase()->withImsakOffset('5');
    }

    public function testAdjustmentsAcceptBoundaryMinutes(): void
    {
        // Adjustment range is [-60, 60]: both ends construct (mutation gate).
        self::assertSame(-60, PrayerAdjustments::empty()->with(Prayer::FAJR, -60)->for(Prayer::FAJR));
        self::assertSame(60, PrayerAdjustments::empty()->with(Prayer::FAJR, 60)->for(Prayer::FAJR));
    }

    public function testCreateRejectsNanElevation(): void
    {
        // NaN is the only input distinguishing || from && in the finiteness
        // guard (INF trips the range disjuncts either way).
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
            NAN,
            false,
            PrayerAdjustments::empty(),
            PrayerAdjustments::empty(),
        );
    }
}
