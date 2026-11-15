<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Contracts\PrayerMetadataCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerCalculationMetadata;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerCalculationOutcome;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Prayer metadata and outcome envelopes: mixed-in common block, inputs, policy.
 */
final class PrayerMetadataTest extends TestCase
{
    private static function coordinates(): Coordinates
    {
        return Coordinates::fromDegrees(3.1390, 101.6869);
    }

    private static function date(): CivilDate
    {
        return CivilDate::fromYmd(2025, 3, 1);
    }

    private static function parameters(): CalculationParameters
    {
        return CalculationMethod::SINGAPORE->defaultParameters();
    }

    private static function metadata(?PolarCircleRule $fallbackRule = null): PrayerCalculationMetadata
    {
        return new PrayerCalculationMetadata(
            CalculationMetadata::create(CalculationContext::PRAYER),
            self::coordinates(),
            self::date(),
            self::coordinates(),
            self::date(),
            self::parameters(),
            $fallbackRule,
        );
    }

    public function testMetadataToArrayWrapsCommonAndInputs(): void
    {
        $array = self::metadata()->toArray();

        self::assertSame([
            'schema_version',
            'context',
            'status',
            'library_version',
            'warnings',
            'sources',
            'input',
            'effective_input',
            'policy',
            'fallback',
            'rounding',
        ], array_keys($array));

        self::assertSame([
            'coordinates' => ['latitude' => 3.1390, 'longitude' => 101.6869],
            'date' => '2025-03-01',
        ], $array['input']);
        self::assertSame($array['input'], $array['effective_input']);
        self::assertSame(self::parameters()->toArray(), $array['policy']);
        self::assertSame(['applied' => false, 'kind' => null, 'rule' => null], $array['fallback']);
        self::assertSame('half_up_to_minute_utc', $array['rounding']);
    }

    public function testMetadataToArrayReportsAPolarFallback(): void
    {
        $fallback = self::metadata(PolarCircleRule::NEAREST_LATITUDE)->toArray()['fallback'];

        self::assertSame([
            'applied' => true,
            'kind' => 'polar',
            'rule' => 'nearest_latitude',
        ], $fallback);
    }

    public function testOutcomeWrapsResultAndMetadata(): void
    {
        $at = static fn(string $time): \DateTimeImmutable => new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
        $schedule = new PrayerSchedule(
            $at('2025-03-01 05:00'),
            $at('2025-03-01 05:10'),
            $at('2025-03-01 06:20'),
            $at('2025-03-01 06:20'),
            $at('2025-03-01 06:35'),
            $at('2025-03-01 06:48'),
            $at('2025-03-01 12:26'),
            $at('2025-03-01 15:39'),
            $at('2025-03-01 18:28'),
            $at('2025-03-01 19:38'),
        );
        $outcome = new PrayerCalculationOutcome($schedule, self::metadata());

        $array = $outcome->toArray();

        self::assertSame(['result', 'metadata'], array_keys($array));
        self::assertSame($schedule->toArray(), $array['result']);
        self::assertSame(self::metadata()->toArray(), $array['metadata']);
    }

    public function testMetadataInterfaceExtendsTheCalculatorContract(): void
    {
        $reflection = new \ReflectionClass(PrayerMetadataCalculatorInterface::class);

        self::assertContains(
            PrayerTimesCalculatorInterface::class,
            $reflection->getInterfaceNames(),
        );

        $method = $reflection->getMethod('calculateWithMetadata');

        self::assertSame(PrayerCalculationOutcome::class, (string) $method->getReturnType());
    }
}
