<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Qibla provenance: the shared envelope plus true-north heading facts.
 *
 * The metadata path never applies a magnetic declination; the null field
 * keeps the shape stable until a sourced magnetic model ships.
 */
final readonly class QiblaCalculationMetadata
{
    /**
     * Heading reference reported by this slice (geodetic true north).
     */
    public const HEADING_REFERENCE = 'true_north';

    /**
     * Bind the shared envelope and the coordinates the caller supplied.
     *
     * @param CalculationMetadata $common Shared envelope.
     * @param Coordinates $coordinates Coordinates the caller supplied.
     */
    public function __construct(
        public CalculationMetadata $common,
        public Coordinates $coordinates,
    ) {}

    /**
     * Wire shape: common six keys, then input, heading reference, declination.
     *
     * @return array{
     *     schema_version: string,
     *     context: string,
     *     status: string,
     *     library_version: string,
     *     warnings: list<array{code: string, message: string, details: array<string, int|float|string|bool|null>}>,
     *     sources: list<array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}>,
     *     input: array{coordinates: array{latitude: float, longitude: float}},
     *     heading_reference: string,
     *     magnetic_declination_degrees: null,
     * }
     */
    public function toArray(): array
    {
        return [
            ...$this->common->toArray(),
            'input' => ['coordinates' => $this->coordinates->toArray()],
            'heading_reference' => self::HEADING_REFERENCE,
            'magnetic_declination_degrees' => null,
        ];
    }
}
