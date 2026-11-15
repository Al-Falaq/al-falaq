<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\ValueObjects;

/**
 * One prayer calculation: the schedule plus its provenance.
 */
final readonly class PrayerCalculationOutcome
{
    /**
     * Bind a result to its metadata.
     *
     * @param PrayerSchedule $result Computed schedule (same value the legacy path returns).
     * @param PrayerCalculationMetadata $metadata Provenance for this calculation.
     */
    public function __construct(
        public PrayerSchedule $result,
        public PrayerCalculationMetadata $metadata,
    ) {}

    /**
     * Wire shape: `result` then `metadata`.
     *
     * @return array{result: array<string, string>, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'result' => $this->result->toArray(),
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
