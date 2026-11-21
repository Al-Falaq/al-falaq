<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel\Provenance;

use AlFalaq\AlFalaq\Astro\Services\MeanSolarCalculator;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\AngleMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\IntervalIsha;
use AlFalaq\AlFalaq\Prayer\Strategies\MakkahHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\MinutesAfterSunsetMaghrib;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestDayHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\Prayer\Strategies\SeventhOfNightCorrection;
use AlFalaq\AlFalaq\Prayer\Strategies\ThrowHandler;
use AlFalaq\AlFalaq\Prayer\Strategies\TwilightAngleCorrection;
use AlFalaq\AlFalaq\Qibla\Strategies\SphericalCalculator;
use AlFalaq\AlFalaq\Qibla\Strategies\Wgs84KarneyCalculator;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every built-in component exposes its registry id as the single source of truth.
 */
final class BuiltInSourceIdentityTest extends TestCase
{
    /**
     * @param object $component Built-in component instance.
     * @param string $expectedId Registry id.
     * @param string|null $expectedVersion Model version, when applicable.
     * @param string|null $expectedRange Model range, when applicable.
     */
    #[DataProvider('componentProvider')]
    public function testBuiltInComponentsExposeTheRegistryId(
        object $component,
        string $expectedId,
        ?string $expectedVersion = null,
        ?string $expectedRange = null,
    ): void {
        self::assertInstanceOf(IdentifiesCalculationSource::class, $component);

        $source = $component->calculationSource();

        self::assertSame($expectedId, $source->id);
        self::assertSame('algorithm', $source->kind->value);
        self::assertSame($expectedVersion, $source->version);
        self::assertSame($expectedRange, $source->range);
    }

    /**
     * @param object $component Built-in component instance.
     * @param string $expectedId Registry id.
     */
    #[DataProvider('componentProvider')]
    public function testEachComponentDeclaresItsSourceIdConstant(object $component, string $expectedId): void
    {
        $reflection = new \ReflectionClass($component);

        self::assertTrue($reflection->hasConstant('SOURCE_ID'));
        self::assertSame($expectedId, $reflection->getConstant('SOURCE_ID'));
    }

    public function testProviderCoversEveryBuiltInComponent(): void
    {
        self::assertCount(15, iterator_to_array(self::componentProvider()));
    }

    public function testMethodDefaultsSourceVersionIsFrozen(): void
    {
        self::assertSame('method-defaults-v1', CalculationMethod::SOURCE_VERSION);
    }

    public function testPresetSourceIdUsesTheCanonicalLowercaseValue(): void
    {
        self::assertSame('prayer.method.singapore', 'prayer.method.' . CalculationMethod::SINGAPORE->value);
        self::assertSame(
            'prayer.method.moon_sighting_committee',
            'prayer.method.' . CalculationMethod::MOON_SIGHTING_COMMITTEE->value,
        );
    }

    /**
     * @return iterable<string, array{object, string, 2?: string|null, 3?: string|null}>
     */
    public static function componentProvider(): iterable
    {
        yield 'solar engine' => [new MeanSolarCalculator(), 'solar.mean-meeus', 'meeus-2ed'];
        yield 'middle of night' => [new MiddleOfNightCorrection(), 'prayer.high-latitude.middle_of_night'];
        yield 'seventh of night' => [new SeventhOfNightCorrection(), 'prayer.high-latitude.seventh_of_night'];
        yield 'twilight angle' => [new TwilightAngleCorrection(), 'prayer.high-latitude.twilight_angle'];
        yield 'seasonal twilight' => [new SeasonAdjustedTwilight(), 'prayer.high-latitude.seasonal_twilight'];
        yield 'nearest latitude' => [new NearestLatitudeHandler(), 'prayer.polar.nearest_latitude'];
        yield 'nearest day' => [new NearestDayHandler(), 'prayer.polar.nearest_day'];
        yield 'makkah' => [new MakkahHandler(), 'prayer.polar.makkah'];
        yield 'throw handler' => [new ThrowHandler(), 'prayer.polar.none'];
        yield 'angle isha' => [new AngleIsha(18.0), 'prayer.isha.angle'];
        yield 'interval isha' => [new IntervalIsha(90), 'prayer.isha.interval'];
        yield 'angle maghrib' => [new AngleMaghrib(4.0), 'prayer.maghrib.angle'];
        yield 'interval maghrib' => [new MinutesAfterSunsetMaghrib(0), 'prayer.maghrib.interval'];
        yield 'qibla spherical' => [new SphericalCalculator(), 'qibla.spherical', 'todhunter-haversine'];
        yield 'qibla wgs84' => [new Wgs84KarneyCalculator(), 'qibla.wgs84-karney', 'geographiclib-2.1'];
    }
}
