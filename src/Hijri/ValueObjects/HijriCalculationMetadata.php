<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\ValueObjects;

use AlFalaq\AlFalaq\Hijri\Enums\HijriDateFrame;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationMetadata;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Hijri-specific metadata: requested instant, resolved civil date, calendar identity.
 *
 * The common envelope owns schema version, status, warnings, and sources; this
 * object records which frame selected the civil date, which calendar converted
 * it, whether an offset store answered, and whether a sunset probe fell back to
 * maghrib alone. The civil frame carries no astronomy inputs; the sunset frame
 * requires the observer and policy that produced its maghrib.
 */
final readonly class HijriCalculationMetadata
{
    /**
     * Fallback kind used when the sunset frame resolves maghrib alone.
     */
    public const FALLBACK_MAGHRIB_ONLY = 'maghrib_only';

    /**
     * Bind validated Hijri provenance.
     *
     * @param CalculationMetadata $common Shared envelope.
     * @param \DateTimeImmutable $requestedInstant Caller's zone-aware instant.
     * @param HijriDateFrame $frame Frame that selected the civil date.
     * @param CivilDate $resolvedCivilDate Gregorian date actually converted.
     * @param CalendarProvenance $calendar Calendar identity.
     * @param int|null $offsetDays OffsetStore answer; null means no store.
     * @param bool $maghribFallbackApplied True when a sunset probe used maghribAt().
     * @param Coordinates|null $coordinates Sunset-frame observer; null for the civil frame.
     * @param CalculationParameters|null $parameters Sunset-frame policy; null for the civil frame.
     */
    public function __construct(
        public CalculationMetadata $common,
        public \DateTimeImmutable $requestedInstant,
        public HijriDateFrame $frame,
        public CivilDate $resolvedCivilDate,
        public CalendarProvenance $calendar,
        public ?int $offsetDays,
        public bool $maghribFallbackApplied,
        public ?Coordinates $coordinates = null,
        public ?CalculationParameters $parameters = null,
    ) {
        if ($common->context !== CalculationContext::HIJRI) {
            throw new ValidationException(sprintf(
                'Hijri metadata requires the hijri calculation context, got "%s".',
                $common->context->value,
            ));
        }

        if ($frame === HijriDateFrame::CIVIL_DATE) {
            if ($coordinates !== null || $parameters !== null) {
                throw new ValidationException('The civil-date frame must not carry coordinates or parameters.');
            }

            if ($maghribFallbackApplied) {
                throw new ValidationException('The civil-date frame cannot report a maghrib-only fallback.');
            }

            return;
        }

        if ($coordinates === null || $parameters === null) {
            throw new ValidationException('The sunset frame requires coordinates and parameters.');
        }
    }

    /**
     * Wire shape: common six keys, then input, effective, calendar, offsets, fallback.
     *
     * @return array{
     *     schema_version: string,
     *     context: string,
     *     status: string,
     *     library_version: string,
     *     warnings: list<array{code: string, message: string, details: array<string, int|float|string|bool|null>}>,
     *     sources: list<array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}>,
     *     input: array{
     *         instant: string,
     *         zone: string,
     *         frame: string,
     *         coordinates: array{latitude: float, longitude: float}|null,
     *         parameters: array<string, mixed>|null,
     *     },
     *     effective: array{gregorian_date: string},
     *     calendar: array{
     *         key: string,
     *         family: string|null,
     *         coverage: string|null,
     *         authority: string|null,
     *         range: string|null,
     *         digest: string|null,
     *         source: string|null,
     *         license: string|null,
     *     },
     *     offsets: array{days: int|null, applied: bool},
     *     fallback: array{applied: bool, kind: string|null},
     * }
     */
    public function toArray(): array
    {
        return [
            ...$this->common->toArray(),
            'input' => [
                'instant' => $this->requestedInstant
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:sP'),
                'zone' => $this->requestedInstant->getTimezone()->getName(),
                'frame' => $this->frame->value,
                'coordinates' => $this->coordinates?->toArray(),
                'parameters' => $this->parameters?->toArray(),
            ],
            'effective' => [
                'gregorian_date' => $this->resolvedCivilDate->isoformat(),
            ],
            'calendar' => $this->calendar->toArray(),
            'offsets' => [
                'days' => $this->offsetDays,
                'applied' => $this->offsetDays !== null,
            ],
            'fallback' => [
                'applied' => $this->maghribFallbackApplied,
                'kind' => $this->maghribFallbackApplied ? self::FALLBACK_MAGHRIB_ONLY : null,
            ],
        ];
    }
}
