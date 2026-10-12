<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\NextPrayerResolver;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\ValueObjects\DisplaySchedule;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Next-prayer badge over real KL/MWL schedules (today + tomorrow boundary).
 */
final class NextPrayerResolverTest extends TestCase
{
    /**
     * @return array{0: PrayerSchedule, 1: PrayerSchedule}
     */
    private function klSchedules(): array
    {
        $coords = Coordinates::fromDegrees(3.1390, 101.6869);
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $todayDate = CivilDate::fromYmd(2025, 1, 15);
        $calc = PrayerTimesCalculator::default();

        return [
            $calc->calculate($coords, $todayDate, $params),
            $calc->calculate($coords, $todayDate->plusDays(1), $params),
        ];
    }

    public function testMidDayNextIsAsr(): void
    {
        [$today, $tomorrow] = $this->klSchedules();

        $now = $today->timeFor(Prayer::DHUHR)->modify('+1 hour');
        self::assertInstanceOf(\DateTimeImmutable::class, $now);

        $result = (new NextPrayerResolver())->resolve($today, $tomorrow, $now);
        $expectedAt = $today->timeFor(Prayer::ASR);

        self::assertSame(Prayer::ASR, $result->prayer);
        self::assertSame($expectedAt->getTimestamp(), $result->at->getTimestamp());
        self::assertSame($expectedAt->getTimestamp() - $now->getTimestamp(), $result->inSeconds);
    }

    public function testPreImsakNextIsTodayImsak(): void
    {
        [$today, $tomorrow] = $this->klSchedules();

        $now = $today->timeFor(Prayer::IMSAK)->modify('-30 minutes');
        self::assertInstanceOf(\DateTimeImmutable::class, $now);

        $result = (new NextPrayerResolver())->resolve($today, $tomorrow, $now);

        self::assertSame(Prayer::IMSAK, $result->prayer);
        self::assertSame($today->timeFor(Prayer::IMSAK)->getTimestamp(), $result->at->getTimestamp());
        self::assertSame($result->at->getTimestamp() - $now->getTimestamp(), $result->inSeconds);
    }

    public function testPostIshaNextIsTomorrowImsak(): void
    {
        [$today, $tomorrow] = $this->klSchedules();

        $now = $today->timeFor(Prayer::ISHA)->modify('+30 minutes');
        self::assertInstanceOf(\DateTimeImmutable::class, $now);

        $result = (new NextPrayerResolver())->resolve($today, $tomorrow, $now);

        self::assertSame(Prayer::IMSAK, $result->prayer);
        self::assertSame($tomorrow->timeFor(Prayer::IMSAK)->getTimestamp(), $result->at->getTimestamp());
        self::assertSame($result->at->getTimestamp() - $now->getTimestamp(), $result->inSeconds);
    }

    public function testExactInstantResolvesToNext(): void
    {
        [$today, $tomorrow] = $this->klSchedules();

        $now = $today->timeFor(Prayer::FAJR);

        $result = (new NextPrayerResolver())->resolve($today, $tomorrow, $now);

        self::assertSame(Prayer::SUNRISE, $result->prayer);
        self::assertSame($today->timeFor(Prayer::SUNRISE)->getTimestamp(), $result->at->getTimestamp());
    }

    public function testNonUtcNowThrows(): void
    {
        [$today, $tomorrow] = $this->klSchedules();

        $nowUtc = $today->timeFor(Prayer::DHUHR)->modify('+1 hour');
        self::assertInstanceOf(\DateTimeImmutable::class, $nowUtc);
        $nowNy = $nowUtc->setTimezone(new \DateTimeZone('America/New_York'));

        $this->expectException(ValidationException::class);

        (new NextPrayerResolver())->resolve($today, $tomorrow, $nowNy);
    }

    public function testDstSpringForwardDayUsesUtcMath(): void
    {
        $coords = Coordinates::fromDegrees(40.7128, -74.0060);
        $params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
        $date = CivilDate::fromYmd(2015, 3, 8);
        $calc = PrayerTimesCalculator::default();
        $today = $calc->calculate($coords, $date, $params);
        $tomorrow = $calc->calculate($coords, $date->plusDays(1), $params);

        $now = $today->timeFor(Prayer::FAJR)->modify('+30 minutes');
        self::assertInstanceOf(\DateTimeImmutable::class, $now);

        $expectedPrayer = null;
        $expectedAt = null;

        foreach ($today->all() as $key => $instant) {
            if ($instant->getTimestamp() > $now->getTimestamp()) {
                $expectedPrayer = Prayer::from($key);
                $expectedAt = $instant;

                break;
            }
        }

        self::assertInstanceOf(Prayer::class, $expectedPrayer);
        self::assertInstanceOf(\DateTimeImmutable::class, $expectedAt);

        $result = (new NextPrayerResolver())->resolve($today, $tomorrow, $now);

        self::assertSame($expectedPrayer, $result->prayer);
        self::assertSame($expectedAt->getTimestamp(), $result->at->getTimestamp());
        self::assertSame($expectedAt->getTimestamp() - $now->getTimestamp(), $result->inSeconds);

        self::assertInstanceOf(DisplaySchedule::class, $today->forZone(new \DateTimeZone('America/New_York')));
    }

    public function testPastAllInstantsThrows(): void
    {
        [$today, $tomorrow] = $this->klSchedules();

        $now = $tomorrow->timeFor(Prayer::ISHA)->modify('+1 hour');
        self::assertInstanceOf(\DateTimeImmutable::class, $now);

        $this->expectException(ValidationException::class);

        (new NextPrayerResolver())->resolve($today, $tomorrow, $now);
    }
}
