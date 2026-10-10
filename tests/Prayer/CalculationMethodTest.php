<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\IntervalIsha;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

/**
 * Frozen Appendix A presets (dual-entered from pin a7baa8b).
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
}
