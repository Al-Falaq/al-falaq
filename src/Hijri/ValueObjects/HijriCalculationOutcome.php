<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\ValueObjects;

/**
 * One Hijri conversion: the date plus its provenance.
 */
final readonly class HijriCalculationOutcome
{
    /**
     * Bind a result and its provenance.
     *
     * @param HijriDate $result Converted date (same value the compact path returns).
     * @param HijriCalculationMetadata $metadata Provenance for this conversion.
     */
    public function __construct(
        public HijriDate $result,
        public HijriCalculationMetadata $metadata,
    ) {}

    /**
     * Wire shape: result then metadata.
     *
     * @return array{
     *     result: array{year: int, month: int, day: int, iso: string},
     *     metadata: array<string, mixed>,
     * }
     */
    public function toArray(): array
    {
        return [
            'result' => $this->result->toArray(),
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
