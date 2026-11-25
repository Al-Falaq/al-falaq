<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\DescribesCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Data\MabimsMonthStarts;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarCoverage;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarFamily;
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Traits\LooksUpMonthStarts;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;
use AlFalaq\AlFalaq\Hijri\ValueObjects\CalendarProvenance;

/**
 * Neo-MABIMS (MY/ID/SG/BN) calendar, backed by per-country generated tables.
 *
 * Coverage is exactly the shipped span (1445-01 … 1500-12 AH) for the bound
 * member state; dates outside it raise `HijriOutOfRangeException`. The country
 * picks a precomputed table — the crescent criteria are never re-evaluated at
 * conversion time. Regenerate with `python3 tools/gen_hijri.py`.
 * Implements `DescribesCalendar`: the generated table is reported with its
 * per-country digest, generator pin, and MIT license.
 */
final class MabimsCalendar implements HijriCalendar, DescribesCalendar
{
    use ShiftsHijriDays;
    use LooksUpMonthStarts;

    /** Generator and commit pin that produced the shipped month-start tables. */
    private const DATA_SOURCE = 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7';

    /** Redistribution status of the generated tables. */
    private const DATA_LICENSE = 'MIT';

    /**
     * Bind the member state whose table this instance resolves.
     *
     * @param MabimsCountry $country Supported member state.
     */
    public function __construct(private readonly MabimsCountry $country) {}

    /**
     * Member state whose table this instance resolves.
     *
     * `name()` is the generic pin key `'mabims'` for every country, so callers
     * keying per-country corrections (for example an `OffsetStore`) must use
     * this accessor to distinguish MY, ID, SG and BN.
     *
     * @return MabimsCountry Bound member state.
     */
    public function country(): MabimsCountry
    {
        return $this->country;
    }

    /**
     * Stable calendar key.
     *
     * @return string Always 'mabims' (the pin's key).
     */
    public function name(): string
    {
        return 'mabims';
    }

    /**
     * Describe this generated-table calendar and the bound member state's data.
     *
     * The key stays the generic `'mabims'` for every country; the member state
     * is carried in `authority`.
     *
     * @return CalendarProvenance MABIMS family, generated-table coverage, per-country digest and pin.
     */
    public function calendarProvenance(): CalendarProvenance
    {
        return new CalendarProvenance(
            $this->name(),
            CalendarFamily::MABIMS,
            CalendarCoverage::GENERATED_TABLE,
            authority: $this->country->value,
            range: sprintf(
                '%d-%02d..%d-%02d AH',
                MabimsMonthStarts::FIRST_YEAR,
                MabimsMonthStarts::FIRST_MONTH,
                MabimsMonthStarts::LAST_YEAR,
                MabimsMonthStarts::LAST_MONTH,
            ),
            digest: MabimsMonthStarts::digestFor($this->country),
            source: self::DATA_SOURCE,
            license: self::DATA_LICENSE,
        );
    }

    /**
     * Generated month-start table for the bound member state.
     *
     * @return array{name: string, range: string, firstYear: int, firstMonth: int, starts: non-empty-list<string>}
     */
    protected function monthStartTable(): array
    {
        return [
            'name' => 'mabims',
            'range' => sprintf(
                '%d–%d AH (%s)',
                MabimsMonthStarts::FIRST_YEAR,
                MabimsMonthStarts::LAST_YEAR,
                $this->country->value,
            ),
            'firstYear' => MabimsMonthStarts::FIRST_YEAR,
            'firstMonth' => MabimsMonthStarts::FIRST_MONTH,
            'starts' => MabimsMonthStarts::forCountry($this->country),
        ];
    }
}
