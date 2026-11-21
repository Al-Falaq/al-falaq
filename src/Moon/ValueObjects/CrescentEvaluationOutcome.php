<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Moon\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Crescent geometry plus the four criterion verdicts and their provenance.
 */
final readonly class CrescentEvaluationOutcome
{
    /**
     * Bind the geometry, the verdict set, and its provenance.
     *
     * @param CrescentGeometry $result Sunset geometry the criteria scored.
     * @param array{yallop: CrescentVerdict, odeh: CrescentVerdict, mabims1992: CrescentVerdict, neomabims2021: CrescentVerdict} $verdicts Verdicts keyed by criterion.
     * @param MoonCalculationMetadata $metadata Provenance for this evaluation.
     */
    public function __construct(
        public CrescentGeometry $result,
        public array $verdicts,
        public MoonCalculationMetadata $metadata,
    ) {
        self::assertVerdicts($verdicts);
    }

    /**
     * Wire shape: geometry and fixed-order verdicts, then metadata.
     *
     * @return array{
     *     result: array{
     *         geometry: array<string, float|null>,
     *         verdicts: array<string, array{criterion: string, visible: bool, score: float, zone: string}>,
     *     },
     *     metadata: array<string, mixed>,
     * }
     */
    public function toArray(): array
    {
        $verdicts = [];

        foreach (MoonCalculationMetadata::CRITERIA as $criterion) {
            $verdicts[$criterion] = $this->verdicts[$criterion]->toArray();
        }

        return [
            'result' => [
                'geometry' => $this->result->toArray(),
                'verdicts' => $verdicts,
            ],
            'metadata' => $this->metadata->toArray(),
        ];
    }

    /**
     * Require exactly the four criterion keys with verdict values.
     *
     * @param array<array-key, mixed> $verdicts Candidate verdict set.
     * @return void Throws when keys or values do not match the fixed contract.
     */
    private static function assertVerdicts(array $verdicts): void
    {
        if (count($verdicts) !== count(MoonCalculationMetadata::CRITERIA)) {
            throw new ValidationException('Crescent evaluation verdicts must contain exactly the four criteria.');
        }

        foreach (MoonCalculationMetadata::CRITERIA as $criterion) {
            if (! array_key_exists($criterion, $verdicts)) {
                throw new ValidationException('Crescent evaluation verdicts must contain exactly the four criteria.');
            }
        }

        foreach ($verdicts as $verdict) {
            if (! $verdict instanceof CrescentVerdict) {
                throw new ValidationException('Crescent evaluation verdicts must be CrescentVerdict instances.');
            }
        }
    }
}
