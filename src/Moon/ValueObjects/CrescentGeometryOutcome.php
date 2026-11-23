<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\ValueObjects;

/**
 * Crescent geometry plus the provenance that explains how it was produced.
 */
final readonly class CrescentGeometryOutcome
{
    /**
     * Bind the geometry and its provenance.
     *
     * @param CrescentGeometry $result Sunset geometry (same value the legacy path returns).
     * @param MoonCalculationMetadata $metadata Provenance for this geometry.
     */
    public function __construct(
        public CrescentGeometry $result,
        public MoonCalculationMetadata $metadata,
    ) {}

    /**
     * Wire shape: result then metadata.
     *
     * @return array{result: array<string, float|null>, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'result' => $this->result->toArray(),
            'metadata' => $this->metadata->toArray(),
        ];
    }
}
