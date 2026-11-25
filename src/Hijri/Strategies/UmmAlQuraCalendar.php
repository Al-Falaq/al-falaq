<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\DescribesCalendar;
use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Data\UmmAlQuraMonthStarts;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarCoverage;
use AlFalaq\AlFalaq\Hijri\Enums\CalendarFamily;
use AlFalaq\AlFalaq\Hijri\Traits\LooksUpMonthStarts;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;
use AlFalaq\AlFalaq\Hijri\ValueObjects\CalendarProvenance;

/**
 * Umm al-Qura (Saudi) calendar, backed by a pin-generated month-start table.
 *
 * Coverage is exactly the shipped span (1423-01 … 1500-12 AH); dates outside it
 * raise `HijriOutOfRangeException` rather than extrapolating. Regenerate the
 * table with `python3 tools/gen_hijri.py`; never edit it by hand.
 * Implements `DescribesCalendar`: the generated table is reported with its
 * digest, generator pin, and MIT license.
 */
final class UmmAlQuraCalendar implements HijriCalendar, DescribesCalendar
{
    use ShiftsHijriDays;
    use LooksUpMonthStarts;

    /** Generator and commit pin that produced the shipped month-start table. */
    private const DATA_SOURCE = 'tools/gen_hijri.py @ a7baa8b202790d3bc84c47a610ac763be6c0b7e7';

    /** Redistribution status of the generated table. */
    private const DATA_LICENSE = 'MIT';

    /**
     * Stable calendar key.
     *
     * @return string Always 'uqu' (the pin's key).
     */
    public function name(): string
    {
        return 'uqu';
    }

    /**
     * Describe this generated-table calendar and the data it ships.
     *
     * @return CalendarProvenance Umm al-Qura family, generated-table coverage, digest and pin.
     */
    public function calendarProvenance(): CalendarProvenance
    {
        return new CalendarProvenance(
            $this->name(),
            CalendarFamily::UMM_AL_QURA,
            CalendarCoverage::GENERATED_TABLE,
            range: sprintf(
                '%d-%02d..%d-%02d AH',
                UmmAlQuraMonthStarts::FIRST_YEAR,
                UmmAlQuraMonthStarts::FIRST_MONTH,
                UmmAlQuraMonthStarts::LAST_YEAR,
                UmmAlQuraMonthStarts::LAST_MONTH,
            ),
            digest: UmmAlQuraMonthStarts::SHA256,
            source: self::DATA_SOURCE,
            license: self::DATA_LICENSE,
        );
    }

    /**
     * Generated month-start table for the Umm al-Qura span.
     *
     * @return array{name: string, range: string, firstYear: int, firstMonth: int, starts: non-empty-list<string>}
     */
    protected function monthStartTable(): array
    {
        return [
            'name' => 'uqu',
            'range' => sprintf(
                '%d–%d AH',
                UmmAlQuraMonthStarts::FIRST_YEAR,
                UmmAlQuraMonthStarts::LAST_YEAR,
            ),
            'firstYear' => UmmAlQuraMonthStarts::FIRST_YEAR,
            'firstMonth' => UmmAlQuraMonthStarts::FIRST_MONTH,
            'starts' => UmmAlQuraMonthStarts::STARTS,
        ];
    }
}
