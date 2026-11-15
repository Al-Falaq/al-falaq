<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\ValueObjects;

use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Prayer-specific metadata: requested/effective inputs, policy, and fallback.
 *
 * The common envelope owns schema version, status, warnings, and sources;
 * this object records what the prayer pipeline used and changed.
 */
final readonly class PrayerCalculationMetadata
{
    /**
     * Rounding policy applied to every reported instant.
     */
    public const ROUNDING = 'half_up_to_minute_utc';

    /**
     * Bind one prayer metadata record.
     *
     * @param CalculationMetadata $common Shared envelope (version, status, warnings, sources).
     * @param Coordinates $requestedCoordinates Coordinates the caller supplied.
     * @param CivilDate $requestedDate Date the caller supplied.
     * @param Coordinates $effectiveCoordinates Coordinates actually computed with.
     * @param CivilDate $effectiveDate Date actually computed with.
     * @param CalculationParameters $parameters Exact policy passed to the calculation.
     * @param PolarCircleRule|null $fallbackRule Applied polar fallback, or null when none ran.
     */
    public function __construct(
        public CalculationMetadata $common,
        public Coordinates $requestedCoordinates,
        public CivilDate $requestedDate,
        public Coordinates $effectiveCoordinates,
        public CivilDate $effectiveDate,
        public CalculationParameters $parameters,
        public ?PolarCircleRule $fallbackRule,
    ) {}

    /**
     * Wire shape: the common six keys first, then the prayer blocks.
     *
     * @return array{
     *     schema_version: string,
     *     context: string,
     *     status: string,
     *     library_version: string,
     *     warnings: list<array{code: string, message: string, details: array<string, int|float|string|bool|null>}>,
     *     sources: list<array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}>,
     *     input: array{coordinates: array{latitude: float, longitude: float}, date: string},
     *     effective_input: array{coordinates: array{latitude: float, longitude: float}, date: string},
     *     policy: array<string, mixed>,
     *     fallback: array{applied: bool, kind: string|null, rule: string|null},
     *     rounding: string,
     * } JSON-safe prayer metadata.
     */
    public function toArray(): array
    {
        return [
            ...$this->common->toArray(),
            'input' => self::inputBlock($this->requestedCoordinates, $this->requestedDate),
            'effective_input' => self::inputBlock($this->effectiveCoordinates, $this->effectiveDate),
            'policy' => $this->parameters->toArray(),
            'fallback' => [
                'applied' => $this->fallbackRule !== null,
                'kind' => $this->fallbackRule !== null ? 'polar' : null,
                'rule' => $this->fallbackRule?->value,
            ],
            'rounding' => self::ROUNDING,
        ];
    }

    /**
     * One requested/effective input block.
     *
     * @param Coordinates $coordinates Input location.
     * @param CivilDate $date Input date.
     * @return array{coordinates: array{latitude: float, longitude: float}, date: string}
     */
    private static function inputBlock(Coordinates $coordinates, CivilDate $date): array
    {
        return [
            'coordinates' => $coordinates->toArray(),
            'date' => $date->isoformat(),
        ];
    }
}
