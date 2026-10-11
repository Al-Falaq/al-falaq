<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Strategies;

use AlFalaq\AlFalaq\Hijri\Contracts\HijriCalendar;
use AlFalaq\AlFalaq\Hijri\Data\MabimsMonthStarts;
use AlFalaq\AlFalaq\Hijri\Enums\MabimsCountry;
use AlFalaq\AlFalaq\Hijri\Traits\LooksUpMonthStarts;
use AlFalaq\AlFalaq\Hijri\Traits\ShiftsHijriDays;

/**
 * Neo-MABIMS (MY/ID/SG/BN) calendar, backed by per-country generated tables.
 *
 * Coverage is exactly the shipped span (1445-01 … 1500-12 AH) for the bound
 * member state; dates outside it raise `HijriOutOfRangeException`. The country
 * picks a precomputed table — the crescent criteria are never re-evaluated at
 * conversion time. Regenerate with `python3 tools/gen_hijri.py`.
 */
final class MabimsCalendar implements HijriCalendar
{
    use ShiftsHijriDays;
    use LooksUpMonthStarts;

    /**
     * Bind the member state whose table this instance resolves.
     *
     * @param MabimsCountry $country Supported member state.
     */
    public function __construct(private readonly MabimsCountry $country) {}

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
