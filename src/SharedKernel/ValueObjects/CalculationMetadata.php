<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationContext;
use AlFalaq\AlFalaq\SharedKernel\Enums\CalculationStatus;
use AlFalaq\AlFalaq\SharedKernel\Enums\WarningCode;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Common metadata envelope: context, derived status, version, warnings, sources.
 *
 * create() is the only construction path. It sorts and de-duplicates the two
 * lists and derives the status from the warning set, so callers cannot build an
 * envelope whose status contradicts its warnings.
 */
final readonly class CalculationMetadata
{
    /**
     * Wire schema version, independent of the package version.
     */
    public const SCHEMA_VERSION = '1.0';

    /**
     * Bind a validated, sorted envelope (private; create() is the entry point).
     *
     * @param CalculationContext $context Calculation context.
     * @param CalculationStatus $status Derived status.
     * @param LibraryVersion $libraryVersion Runtime library version.
     * @param list<CalculationWarning> $warnings Sorted, de-duplicated warnings.
     * @param list<CalculationSource> $sources Sorted, de-duplicated sources.
     */
    private function __construct(
        public CalculationContext $context,
        public CalculationStatus $status,
        public LibraryVersion $libraryVersion,
        public array $warnings,
        public array $sources,
    ) {}

    /**
     * Build the envelope from raw warnings and sources.
     *
     * @param CalculationContext $context Calculation context.
     * @param list<CalculationWarning> $warnings Raw warnings; sorted and de-duplicated here.
     * @param list<CalculationSource> $sources Raw sources; sorted and de-duplicated here.
     * @param LibraryVersion|null $libraryVersion Version to report; null uses the current runtime version.
     * @return self Validated envelope with a derived status.
     */
    public static function create(
        CalculationContext $context,
        array $warnings = [],
        array $sources = [],
        ?LibraryVersion $libraryVersion = null,
    ): self {
        self::assertWarningList($warnings);
        self::assertSourceList($sources);

        return new self(
            $context,
            self::deriveStatus($warnings),
            $libraryVersion ?? LibraryVersion::current(),
            self::uniqueWarnings(self::sortWarnings($warnings)),
            self::uniqueSources(self::sortSources($sources)),
        );
    }

    /**
     * Wire shape with the fixed six-key order.
     *
     * @return array{
     *     schema_version: string,
     *     context: string,
     *     status: string,
     *     library_version: string,
     *     warnings: list<array{code: string, message: string, details: array<string, int|float|string|bool|null>}>,
     *     sources: list<array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}>,
     * } Schema version, context, status, version, warnings, sources.
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'context' => $this->context->value,
            'status' => $this->status->value,
            'library_version' => (string) $this->libraryVersion,
            'warnings' => array_map(
                static fn(CalculationWarning $warning): array => $warning->toArray(),
                $this->warnings,
            ),
            'sources' => array_map(
                static fn(CalculationSource $source): array => $source->toArray(),
                $this->sources,
            ),
        ];
    }

    /**
     * Derive the highest-ranked status from the warning set.
     *
     * @param list<CalculationWarning> $warnings Validated warnings.
     * @return CalculationStatus Derived status; complete when no warning ranks.
     */
    private static function deriveStatus(array $warnings): CalculationStatus
    {
        $status = CalculationStatus::COMPLETE;

        foreach ($warnings as $warning) {
            $candidate = match ($warning->code) {
                WarningCode::DELTA_T_EXTRAPOLATED => CalculationStatus::EXTRAPOLATED,
                WarningCode::POLAR_FALLBACK_APPLIED,
                WarningCode::SUNSET_FRAME_MAGHRIB_FALLBACK => CalculationStatus::FALLBACK_APPLIED,
                WarningCode::MOONSET_INDETERMINATE => CalculationStatus::INDETERMINATE,
                WarningCode::CRESCENT_CRITERION_IS_NOT_OBSERVATION => CalculationStatus::DECISION_REQUIRED,
                WarningCode::GENERATED_CALENDAR_TABLE,
                WarningCode::TABULAR_CALENDAR_ESTIMATE,
                WarningCode::UNIDENTIFIED_CALCULATION_SOURCE => null,
            };

            if ($candidate !== null && self::rank($candidate) > self::rank($status)) {
                $status = $candidate;
            }
        }

        return $status;
    }

    /**
     * Status precedence rank (higher wins).
     *
     * @param CalculationStatus $status Status to rank.
     * @return int Zero for complete, four for decision-required.
     */
    private static function rank(CalculationStatus $status): int
    {
        return match ($status) {
            CalculationStatus::COMPLETE => 0,
            CalculationStatus::EXTRAPOLATED => 1,
            CalculationStatus::FALLBACK_APPLIED => 2,
            CalculationStatus::INDETERMINATE => 3,
            CalculationStatus::DECISION_REQUIRED => 4,
        };
    }

    /**
     * Sort warnings by code, message, then serialized details.
     *
     * @param list<CalculationWarning> $warnings Validated warnings.
     * @return list<CalculationWarning> Sorted copy.
     */
    private static function sortWarnings(array $warnings): array
    {
        usort(
            $warnings,
            static fn(CalculationWarning $a, CalculationWarning $b): int => self::compareWarnings($a, $b),
        );

        return $warnings;
    }

    /**
     * Total ordering for warnings using textual, not numeric-string, comparison.
     *
     * @param CalculationWarning $a Left warning.
     * @param CalculationWarning $b Right warning.
     * @return int Negative, zero, or positive comparison result.
     */
    private static function compareWarnings(CalculationWarning $a, CalculationWarning $b): int
    {
        $byCode = strcmp($a->code->value, $b->code->value);

        if ($byCode !== 0) {
            return $byCode;
        }

        $byMessage = strcmp($a->message, $b->message);

        if ($byMessage !== 0) {
            return $byMessage;
        }

        return strcmp(
            serialize($a->toArray()['details']),
            serialize($b->toArray()['details']),
        );
    }

    /**
     * Sort sources by id, kind, version (null last), then digest and range.
     *
     * @param list<CalculationSource> $sources Validated sources.
     * @return list<CalculationSource> Sorted copy.
     */
    private static function sortSources(array $sources): array
    {
        usort(
            $sources,
            static fn(CalculationSource $a, CalculationSource $b): int => self::compareSources($a, $b),
        );

        return $sources;
    }

    /**
     * Total ordering for sources with null last and textual field comparison.
     *
     * @param CalculationSource $a Left source.
     * @param CalculationSource $b Right source.
     * @return int Negative, zero, or positive comparison result.
     */
    private static function compareSources(CalculationSource $a, CalculationSource $b): int
    {
        $byId = strcmp($a->id, $b->id);

        if ($byId !== 0) {
            return $byId;
        }

        $byKind = strcmp($a->kind->value, $b->kind->value);

        if ($byKind !== 0) {
            return $byKind;
        }

        $byVersion = self::compareNullableText($a->version, $b->version);

        if ($byVersion !== 0) {
            return $byVersion;
        }

        $byDigest = self::compareNullableText($a->digest, $b->digest);

        if ($byDigest !== 0) {
            return $byDigest;
        }

        return self::compareNullableText($a->range, $b->range);
    }

    /**
     * Compare two optional textual fields, keeping null after every value.
     *
     * @param string|null $a Left value.
     * @param string|null $b Right value.
     * @return int Negative, zero, or positive comparison result.
     */
    private static function compareNullableText(?string $a, ?string $b): int
    {
        if ($a === null || $b === null) {
            return ($a === null ? 1 : 0) <=> ($b === null ? 1 : 0);
        }

        return strcmp($a, $b);
    }

    /**
     * Collapse identical warnings after sorting.
     *
     * @param list<CalculationWarning> $warnings Sorted warnings.
     * @return list<CalculationWarning> One entry per distinct warning.
     */
    private static function uniqueWarnings(array $warnings): array
    {
        $seen = [];
        $unique = [];

        foreach ($warnings as $warning) {
            $key = serialize($warning->toArray());

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $warning;
        }

        return $unique;
    }

    /**
     * Collapse identical sources after sorting.
     *
     * @param list<CalculationSource> $sources Sorted sources.
     * @return list<CalculationSource> One entry per distinct source.
     */
    private static function uniqueSources(array $sources): array
    {
        $seen = [];
        $unique = [];

        foreach ($sources as $source) {
            $key = serialize($source->toArray());

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $source;
        }

        return $unique;
    }

    /**
     * Require a list of warnings.
     *
     * @param mixed $warnings Candidate list.
     * @return void Throws when the value is not a list of warnings.
     */
    private static function assertWarningList(mixed $warnings): void
    {
        if (! is_array($warnings) || ! array_is_list($warnings)) {
            throw new ValidationException('Calculation metadata warnings must be a list.');
        }

        foreach ($warnings as $warning) {
            if (! $warning instanceof CalculationWarning) {
                throw new ValidationException('Calculation metadata warnings must be CalculationWarning instances.');
            }
        }
    }

    /**
     * Require a list of sources.
     *
     * @param mixed $sources Candidate list.
     * @return void Throws when the value is not a list of sources.
     */
    private static function assertSourceList(mixed $sources): void
    {
        if (! is_array($sources) || ! array_is_list($sources)) {
            throw new ValidationException('Calculation metadata sources must be a list.');
        }

        foreach ($sources as $source) {
            if (! $source instanceof CalculationSource) {
                throw new ValidationException('Calculation metadata sources must be CalculationSource instances.');
            }
        }
    }
}
