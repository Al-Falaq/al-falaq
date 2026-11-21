<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Enums\SourceKind;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Moon provenance: the shared envelope plus geometry, ΔT, and criteria facts.
 *
 * The ΔT calibrated bounds travel through the provider source's `range`; this
 * namespace must not import the Astro provider (layering gate).
 */
final readonly class MoonCalculationMetadata
{
    /**
     * Geometry was computed by the calculator for the reported inputs.
     */
    public const GEOMETRY_CALCULATED = 'calculated';

    /**
     * Geometry was supplied by the caller; its provenance is unknown here.
     */
    public const GEOMETRY_CALLER_SUPPLIED = 'caller_supplied';

    /**
     * ΔT came from the calibrated provider.
     */
    public const DELTA_T_PROVIDER = 'provider';

    /**
     * ΔT was supplied by the caller.
     */
    public const DELTA_T_OVERRIDE = 'override';

    /**
     * Fixed criterion evaluation order.
     *
     * @var list<string>
     */
    public const CRITERIA = ['yallop', 'odeh', 'mabims1992', 'neomabims2021'];

    /**
     * Bind validated provenance fields.
     *
     * @param CalculationMetadata $common Shared envelope.
     * @param Coordinates|null $coordinates Requested coordinates; null for caller-supplied geometry.
     * @param CivilDate|null $date Requested civil date; null for caller-supplied geometry.
     * @param float|null $deltaTSeconds ΔT used; null for caller-supplied geometry.
     * @param CalculationSource|null $deltaTSource Provider or caller override; null for caller-supplied geometry.
     * @param string $geometrySource One of the GEOMETRY_* constants.
     * @param list<string> $criteria Empty or the fixed CRITERIA order.
     */
    public function __construct(
        public CalculationMetadata $common,
        public ?Coordinates $coordinates,
        public ?CivilDate $date,
        public ?float $deltaTSeconds,
        public ?CalculationSource $deltaTSource,
        public string $geometrySource,
        public array $criteria,
    ) {
        if ($geometrySource !== self::GEOMETRY_CALCULATED && $geometrySource !== self::GEOMETRY_CALLER_SUPPLIED) {
            throw new ValidationException(sprintf('Unknown Moon geometry source "%s".', $geometrySource));
        }

        if ($criteria !== [] && $criteria !== self::CRITERIA) {
            throw new ValidationException('Moon criteria must be empty or the fixed CRITERIA order.');
        }

        if ($geometrySource === self::GEOMETRY_CALCULATED) {
            self::assertCalculatedFields($coordinates, $date, $deltaTSeconds, $deltaTSource);
        } elseif ($coordinates !== null || $date !== null || $deltaTSeconds !== null || $deltaTSource !== null) {
            throw new ValidationException('Caller-supplied geometry must not carry coordinates, date, ΔT, or a ΔT source.');
        }
    }

    /**
     * Wire shape: common six keys, then input, delta_t, geometry source, criteria.
     *
     * @return array{
     *     schema_version: string,
     *     context: string,
     *     status: string,
     *     library_version: string,
     *     warnings: list<array{code: string, message: string, details: array<string, int|float|string|bool|null>}>,
     *     sources: list<array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}>,
     *     input: array{coordinates: array{latitude: float, longitude: float}|null, date: string|null},
     *     delta_t: array{seconds: float, origin: string, calibrated_from_year: float|null, calibrated_to_year: float|null}|null,
     *     geometry_source: string,
     *     criteria: list<string>,
     * }
     */
    public function toArray(): array
    {
        return [
            ...$this->common->toArray(),
            'input' => [
                'coordinates' => $this->coordinates?->toArray(),
                'date' => $this->date?->isoformat(),
            ],
            'delta_t' => $this->deltaTBlock(),
            'geometry_source' => $this->geometrySource,
            'criteria' => $this->criteria,
        ];
    }

    /**
     * The serialized ΔT block, or null when no ΔT provenance exists.
     *
     * @return array{seconds: float, origin: string, calibrated_from_year: float|null, calibrated_to_year: float|null}|null
     */
    private function deltaTBlock(): ?array
    {
        if ($this->deltaTSeconds === null || $this->deltaTSource === null) {
            return null;
        }

        if ($this->deltaTSource->kind === SourceKind::ALGORITHM) {
            [$from, $to] = self::calibratedBounds($this->deltaTSource);

            return [
                'seconds' => $this->deltaTSeconds,
                'origin' => self::DELTA_T_PROVIDER,
                'calibrated_from_year' => $from,
                'calibrated_to_year' => $to,
            ];
        }

        return [
            'seconds' => $this->deltaTSeconds,
            'origin' => self::DELTA_T_OVERRIDE,
            'calibrated_from_year' => null,
            'calibrated_to_year' => null,
        ];
    }

    /**
     * Require the four fields a calculated geometry must report.
     *
     * @param Coordinates|null $coordinates Requested coordinates.
     * @param CivilDate|null $date Requested date.
     * @param float|null $deltaTSeconds ΔT used.
     * @param CalculationSource|null $deltaTSource ΔT source.
     * @return void Throws when a field is missing or the source kind is unsupported.
     */
    private static function assertCalculatedFields(
        ?Coordinates $coordinates,
        ?CivilDate $date,
        ?float $deltaTSeconds,
        ?CalculationSource $deltaTSource,
    ): void {
        if ($coordinates === null || $date === null || $deltaTSeconds === null || $deltaTSource === null) {
            throw new ValidationException('Calculated Moon geometry requires coordinates, date, ΔT, and a ΔT source.');
        }

        if (! is_finite($deltaTSeconds)) {
            throw new ValidationException('Moon ΔT seconds must be finite.');
        }

        if ($deltaTSource->kind === SourceKind::ALGORITHM) {
            self::calibratedBounds($deltaTSource);

            return;
        }

        if ($deltaTSource->kind !== SourceKind::CALLER_INPUT) {
            throw new ValidationException('Moon ΔT source must be an algorithm provider or a caller input.');
        }
    }

    /**
     * Parse the provider's documented `from..to` calibrated range.
     *
     * @param CalculationSource $source Algorithm source carrying the range.
     * @return array{float, float} Inclusive calibrated bounds.
     */
    private static function calibratedBounds(CalculationSource $source): array
    {
        $range = $source->range;

        if ($range === null || preg_match('/\A(-?\d+(?:\.\d+)?)\.\.(-?\d+(?:\.\d+)?)\z/', $range, $matches) !== 1) {
            throw new ValidationException('ΔT provider source must carry a "from..to" calibrated range.');
        }

        $from = (float) $matches[1];
        $to = (float) $matches[2];

        if (! is_finite($from) || ! is_finite($to) || $from > $to) {
            throw new ValidationException('ΔT provider calibrated range must be finite and ordered.');
        }

        return [$from, $to];
    }
}
