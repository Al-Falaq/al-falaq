<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Qibla;

use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\Qibla\ValueObjects\QiblaCalculationMetadata;
use AlFalaq\AlFalaq\Qibla\ValueObjects\QiblaCalculationOutcome;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\InvalidCoordinatesException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\LibraryVersion;
use PHPUnit\Framework\TestCase;

/**
 * Qibla metadata: true-north provenance, geodesy sources, and outcome shape.
 */
final class QiblaMetadataTest extends TestCase
{
    private static function kualaLumpur(): Coordinates
    {
        return Coordinates::fromDegrees(3.1390, 101.6869);
    }

    public function testSphericalOutcomeIsCompleteWithTheRegistrySource(): void
    {
        $outcome = QiblaCalculator::default()->calculateWithMetadata(self::kualaLumpur());
        $array = $outcome->metadata->toArray();

        self::assertSame([
            'schema_version',
            'context',
            'status',
            'library_version',
            'warnings',
            'sources',
            'input',
            'heading_reference',
            'magnetic_declination_degrees',
        ], array_keys($array));

        self::assertSame('1.0', $array['schema_version']);
        self::assertSame('qibla', $array['context']);
        self::assertSame('complete', $array['status']);
        self::assertSame(LibraryVersion::CURRENT, $array['library_version']);
        self::assertSame([], $array['warnings']);
        self::assertSame([[
            'id' => 'qibla.spherical',
            'kind' => 'algorithm',
            'version' => 'todhunter-haversine',
            'digest' => null,
            'range' => null,
        ]], $array['sources']);
        self::assertSame(['coordinates' => self::kualaLumpur()->toArray()], $array['input']);
        self::assertSame('true_north', $array['heading_reference']);
        self::assertNull($array['magnetic_declination_degrees']);

        $json = json_encode($outcome->toArray(), JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('Astro\\', $json);
    }

    public function testWgs84OutcomeReportsKarneySource(): void
    {
        $outcome = QiblaCalculator::wgs84()->calculateWithMetadata(self::kualaLumpur());

        self::assertSame([[
            'id' => 'qibla.wgs84-karney',
            'kind' => 'algorithm',
            'version' => 'geographiclib-2.1',
            'digest' => null,
            'range' => null,
        ]], $outcome->metadata->toArray()['sources']);
    }

    public function testMetadataRejectsAnotherCalculationContext(): void
    {
        $this->expectException(ValidationException::class);

        new QiblaCalculationMetadata(
            CalculationMetadata::create(CalculationContext::MOON),
            self::kualaLumpur(),
        );
    }

    public function testOutcomeToArrayWrapsResultAndMetadata(): void
    {
        $outcome = QiblaCalculator::default()->calculateWithMetadata(self::kualaLumpur());
        $array = $outcome->toArray();

        self::assertSame(['result', 'metadata'], array_keys($array));
        self::assertSame($outcome->result->toArray(), $array['result']);
        self::assertSame($outcome->metadata->toArray(), $array['metadata']);
    }

    public function testLegacyResultEqualsMetadataResult(): void
    {
        $places = [
            'Kuala Lumpur' => self::kualaLumpur(),
            'Makkah' => Coordinates::fromDegrees(21.4225241, 39.8261818),
            'Raleigh' => Coordinates::fromDegrees(35.7750, -78.6336),
            'Tromsø' => Coordinates::fromDegrees(69.6496, 18.9553),
        ];

        foreach ([QiblaCalculator::default(), QiblaCalculator::wgs84()] as $calculator) {
            foreach ($places as $place => $coordinates) {
                self::assertSame(
                    $calculator->calculate($coordinates)->toArray(),
                    $calculator->calculateWithMetadata($coordinates)->result->toArray(),
                    sprintf('Legacy and metadata results diverged for %s.', $place),
                );
            }
        }
    }

    public function testArrayAndValueObjectInputsAgree(): void
    {
        $calculator = QiblaCalculator::default();

        self::assertSame(
            $calculator->calculateWithMetadata(self::kualaLumpur())->toArray(),
            $calculator->calculateWithMetadata([3.1390, 101.6869])->toArray(),
        );
    }

    public function testMalformedArrayStillThrows(): void
    {
        $calculator = QiblaCalculator::default();
        // The static signature requires a two-slot tuple; reflection lets the
        // test feed the runtime guard the malformed shape callers actually pass.
        $calculate = new \ReflectionMethod($calculator, 'calculate');
        $withMetadata = new \ReflectionMethod($calculator, 'calculateWithMetadata');

        self::assertThrows(
            InvalidCoordinatesException::class,
            static fn() => $calculate->invoke($calculator, [3.1390]),
        );
        self::assertThrows(
            InvalidCoordinatesException::class,
            static fn() => $withMetadata->invoke($calculator, [3.1390]),
        );
    }

    public function testUnidentifiedGeodesyIsReportedAsCustomWithAWarning(): void
    {
        $calculator = new QiblaCalculator(self::unidentifiedGeodesy());
        $outcome = $calculator->calculateWithMetadata(self::kualaLumpur());
        $array = $outcome->metadata->toArray();

        self::assertSame([[
            'id' => 'custom.geodesy',
            'kind' => 'algorithm',
            'version' => null,
            'digest' => null,
            'range' => null,
        ]], $array['sources']);
        self::assertCount(1, $array['warnings']);
        self::assertSame('unidentified_calculation_source', $array['warnings'][0]['code']);
        self::assertSame(
            ['component' => 'geodesy', 'source_id' => 'custom.geodesy'],
            $array['warnings'][0]['details'],
        );
        self::assertSame('complete', $array['status']);

        $legacy = $calculator->calculate(self::kualaLumpur());

        self::assertSame(12.0, $legacy->direction);
        self::assertSame(34.0, $legacy->distanceKm);
    }

    public function testIdentifiedCustomGeodesyIsReportedWithoutAWarning(): void
    {
        $calculator = new QiblaCalculator(self::identifiedGeodesy());
        $outcome = $calculator->calculateWithMetadata(self::kualaLumpur());

        self::assertSame('custom.test-geodesy', self::sourceIds($outcome)[0]);
        self::assertSame([], $outcome->metadata->common->warnings);
    }

    /**
     * Geodesy double without a source identity.
     *
     * @return GeodesyCalculator Custom geodesy.
     */
    private static function unidentifiedGeodesy(): GeodesyCalculator
    {
        return new class implements GeodesyCalculator {
            public function inverse(Coordinates $from): array
            {
                return ['direction' => 12.0, 'distanceKm' => 34.0];
            }
        };
    }

    /**
     * Same double with an explicit custom source identity.
     *
     * @return GeodesyCalculator Custom geodesy implementing IdentifiesCalculationSource.
     */
    private static function identifiedGeodesy(): GeodesyCalculator
    {
        return new class implements GeodesyCalculator, IdentifiesCalculationSource {
            public function inverse(Coordinates $from): array
            {
                return ['direction' => 12.0, 'distanceKm' => 34.0];
            }

            public function calculationSource(): CalculationSource
            {
                return CalculationSource::algorithm('custom.test-geodesy', 'v1');
            }
        };
    }

    /**
     * Ids of the sorted sources in one outcome.
     *
     * @param QiblaCalculationOutcome $outcome Outcome to inspect.
     * @return list<string>
     */
    private static function sourceIds(QiblaCalculationOutcome $outcome): array
    {
        return array_map(
            static fn(CalculationSource $source): string => $source->id,
            $outcome->metadata->common->sources,
        );
    }

    /**
     * Assert a callable throws exactly the expected exception class.
     *
     * @param class-string<\Throwable> $expected Expected exception class.
     * @param callable(): mixed $call Code under test.
     * @return void
     */
    private static function assertThrows(string $expected, callable $call): void
    {
        try {
            $call();
        } catch (\Throwable $throwable) {
            self::assertInstanceOf($expected, $throwable);

            return;
        }

        self::fail(sprintf('Expected %s, got no exception.', $expected));
    }
}
