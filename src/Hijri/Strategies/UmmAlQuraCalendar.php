<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Data\UmmAlQuraMonthStarts;
use AlFalaq\AlFalaq\Hijri\Traits\LooksUpMonthStarts;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;

/**
 * Umm al-Qura (Saudi) calendar, backed by a pin-generated month-start table.
 *
 * Coverage is exactly the shipped span (1423-01 … 1500-12 AH); dates outside it
 * raise `HijriOutOfRangeException` rather than extrapolating. Regenerate the
 * table with `python3 tools/gen_hijri.py`; never edit it by hand.
 */
final class UmmAlQuraCalendar implements HijriCalendar
{
    use ShiftsHijriDays;
    use LooksUpMonthStarts;

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
