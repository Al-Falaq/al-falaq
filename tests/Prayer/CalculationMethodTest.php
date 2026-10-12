<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\IntervalIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Frozen method presets (dual-entered from pin a7baa8b).
 */
final class CalculationMethodTest extends TestCase
{
    public function testMwlRowMatchesPin(): void
    {
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();

        self::assertSame(18.0, $params->fajrAngle);
        self::assertInstanceOf(AngleIsha::class, $params->ishaRule);
        self::assertSame(17.0, $params->ishaRule->angle());
        self::assertSame(10, $params->imsakOffsetMinutes);
        self::assertSame(15, $params->ishraqOffsetMinutes);
        self::assertSame(28, $params->dhuhaOffsetMinutes);
        self::assertSame(1, $params->methodAdjustments->for(Prayer::DHUHR));
        self::assertSame(Madhab::SHAFI, $params->madhab);
    }

    public function testUquRowUsesIntervalIsha(): void
    {
        $params = CalculationMethod::UMM_AL_QURA->defaultParameters();

        self::assertInstanceOf(IntervalIsha::class, $params->ishaRule);
        self::assertSame(90, $params->ishaRule->minutesAfterMaghrib());
        self::assertSame(18.5, $params->fajrAngle);
    }

    public function testTurkeyImsakOffsetIsZero(): void
    {
        // Diyanet Imsak IS the fajr instant (T2: turkey CSV imsak==fajr).
        self::assertSame(0, CalculationMethod::TURKEY->defaultParameters()->imsakOffsetMinutes);
    }

    public function testNoneHasNoDefaults(): void
    {
        $this->expectException(ConfigurationException::class);

        CalculationMethod::NONE->defaultParameters();
    }

    public function testAllShippedRowsConstruct(): void
    {
        $count = 0;

        foreach (CalculationMethod::cases() as $method) {
            if ($method === CalculationMethod::NONE) {
                continue;
            }

            $method->defaultParameters();
            $count++;
        }

        self::assertSame(17, $count);
    }

    public function testMadhabShadowLengths(): void
    {
        self::assertSame(1.0, Madhab::SHAFI->shadowLength());
        self::assertSame(2.0, Madhab::HANAFI->shadowLength());
    }

    public function testMinutesAfterSunsetMaghribReadsDelayAndHasNoAngle(): void
    {
        $rule = new MinutesAfterSunsetMaghrib(3);

        self::assertSame(3, $rule->minutesAfterSunset());
        self::assertNull($rule->angle());
    }

    public function testMinutesAfterSunsetMaghribDefaultsToZero(): void
    {
        // The no-delay default is 0, not ±1 (mutation gate).
        self::assertSame(0, (new MinutesAfterSunsetMaghrib())->minutesAfterSunset());
    }

    public function testTurkeyRowMatchesPin(): void
    {
        // Diyanet preset literals (mutation gate: every literal pinned).
        $params = CalculationMethod::TURKEY->defaultParameters();

        self::assertSame(18.0, $params->fajrAngle);
        self::assertSame(17.0, $params->ishaRule->angle());
        self::assertSame(0, $params->maghribRule->minutesAfterSunset());
        self::assertSame(0, $params->imsakOffsetMinutes);
        self::assertSame(15, $params->ishraqOffsetMinutes);
        self::assertSame(28, $params->dhuhaOffsetMinutes);
        self::assertSame(0.0, $params->elevationM);
    }

    public function testKemenagRowMatchesPin(): void
    {
        $params = CalculationMethod::KEMENAG->defaultParameters();

        self::assertSame(20.0, $params->fajrAngle);
        self::assertSame(18.0, $params->ishaRule->angle());
        self::assertSame(0, $params->maghribRule->minutesAfterSunset());
        self::assertSame(10, $params->imsakOffsetMinutes);
        self::assertSame(15, $params->ishraqOffsetMinutes);
        self::assertSame(28, $params->dhuhaOffsetMinutes);
        self::assertSame(0.0, $params->elevationM);
        self::assertSame(2, $params->methodAdjustments->for(Prayer::MAGHRIB));
        self::assertSame(2, $params->methodAdjustments->for(Prayer::ISHA));
    }

    public function testMuisRowMatchesPin(): void
    {
        $params = CalculationMethod::MUIS->defaultParameters();

        self::assertSame(20.0, $params->fajrAngle);
        self::assertSame(18.0, $params->ishaRule->angle());
        self::assertSame(10, $params->imsakOffsetMinutes);
        self::assertSame(15, $params->ishraqOffsetMinutes);
        self::assertSame(28, $params->dhuhaOffsetMinutes);
    }

    public function testBruneiRowMatchesPin(): void
    {
        $params = CalculationMethod::BRUNEI->defaultParameters();

        self::assertSame(15, $params->ishraqOffsetMinutes);
        self::assertSame(28, $params->dhuhaOffsetMinutes);
        self::assertSame(0.0, $params->elevationM);
    }
}
