<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Qibla\ValueObjects;

/**
 * Qibla result plus the provenance that explains which geodesy ran.
 */
final readonly class QiblaCalculationOutcome
{
    /**
     * Bind the computed result and its provenance.
     *
     * @param QiblaResult $result Computed result (same value the legacy path returns).
     * @param QiblaCalculationMetadata $metadata Provenance for this calculation.
     */
    public function __construct(
        public QiblaResult $result,
        public QiblaCalculationMetadata $metadata,
    ) {}

    /**
     * Wire shape: result then metadata.
     *
     * @return array{result: array{direction_degrees: float, distance_km: float}, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'result' => $this->result->toArray(),
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
