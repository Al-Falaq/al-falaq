<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Wiring;

use AlFalaq\AlFalaq\Prayer\Contracts\NextPrayerResolverInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\TimetableServiceInterface;
use AlFalaq\AlFalaq\Prayer\Services\NextPrayerResolver;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\SphericalCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\Wgs84KarneyCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Manual wiring smoke test: every service is constructed with plain
 * `new` / named factories. No container, no service provider, no config
 * files — the library must be usable in a single expression.
 */
final class CalculatorWiringTest extends TestCase
{
    public function testQiblaDefaultWiresSphericalGeodesy(): void
    {
        self::assertInstanceOf(SphericalCalculator::class, QiblaCalculator::default()->geodesy());
    }

    public function testQiblaWgs84WiresKarneyGeodesy(): void
    {
        self::assertInstanceOf(Wgs84KarneyCalculator::class, QiblaCalculator::wgs84()->geodesy());
    }

    public function testPrayerCalculatorSatisfiesItsContract(): void
    {
        self::assertInstanceOf(PrayerTimesCalculatorInterface::class, PrayerTimesCalculator::default());
    }

    public function testResolverAndTimetableSatisfyTheirContracts(): void
    {
        self::assertInstanceOf(NextPrayerResolverInterface::class, new NextPrayerResolver());
        self::assertInstanceOf(TimetableServiceInterface::class, new TimetableService());
    }
}
