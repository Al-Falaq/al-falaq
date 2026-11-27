<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\ValueObjects;

use AlFalaq\AlFalaq\Hijri\Enums\CalendarCoverage;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarFamily;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Auditable identity of a Hijri calendar: key, family, coverage, and data.
 *
 * The key is the calendar's stable key (normally its `name()`), not a
 * `CalculationSource` id, so a custom calendar may use any non-blank label.
 * A `DescribesCalendar` calendar supplies it through `calendarProvenance()`;
 * it is stored verbatim, without trimming or normalization. Family and
 * coverage are published together or omitted together; table-backed coverage
 * requires a range and a lowercase SHA-256 digest, and an official table
 * additionally requires an authority, source, and license.
 */
final readonly class CalendarProvenance
{
    /**
     * Bind a validated calendar identity.
     *
     * @param string $key Calendar's stable key (normally its name()), not a CalculationSource id.
     * @param CalendarFamily|null $family Family, or null for an undescribed custom calendar.
     * @param CalendarCoverage|null $coverage Coverage, or null for an undescribed custom calendar.
     * @param string|null $authority Member-state code for MABIMS or the publishing authority for an official table; null otherwise.
     * @param string|null $range Covered span, required for table-backed calendars.
     * @param string|null $digest Lowercase SHA-256, required for table-backed calendars.
     * @param string|null $source Human-readable generator/pin or dataset reference.
     * @param string|null $license Redistribution status.
     */
    public function __construct(
        public string $key,
        public ?CalendarFamily $family,
        public ?CalendarCoverage $coverage,
        public ?string $authority = null,
        public ?string $range = null,
        public ?string $digest = null,
        public ?string $source = null,
        public ?string $license = null,
    ) {
        if (trim($key) === '') {
            throw new ValidationException('Calendar provenance key must not be blank.');
        }

        if (($family === null) !== ($coverage === null)) {
            throw new ValidationException('Calendar provenance family and coverage must both be set or both be null.');
        }

        if ($coverage === CalendarCoverage::GENERATED_TABLE || $coverage === CalendarCoverage::OFFICIAL_TABLE) {
            if ($digest === null || preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
                throw new ValidationException('Table-backed calendar provenance requires a lowercase SHA-256 digest.');
            }

            if ($range === null || trim($range) === '') {
                throw new ValidationException('Table-backed calendar provenance requires a coverage range.');
            }
        }

        if ($coverage === CalendarCoverage::OFFICIAL_TABLE) {
            foreach (['authority' => $authority, 'source' => $source, 'license' => $license] as $name => $value) {
                if ($value === null || trim($value) === '') {
                    throw new ValidationException(sprintf('Official table provenance requires a %s.', $name));
                }
            }
        }
    }

    /**
     * Wire shape in constructor order; nullable facts are always present.
     *
     * @return array{
     *     key: string,
     *     family: string|null,
     *     coverage: string|null,
     *     authority: string|null,
     *     range: string|null,
     *     digest: string|null,
     *     source: string|null,
     *     license: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'family' => $this->family?->value,
            'coverage' => $this->coverage?->value,
            'authority' => $this->authority,
            'range' => $this->range,
            'digest' => $this->digest,
            'source' => $this->source,
            'license' => $this->license,
        ];
    }
}
