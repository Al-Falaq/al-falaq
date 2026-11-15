<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Polar NONE rule: echo inputs so the calculator re-probe throws.
 *
 * The calculator never emits this identity: the NONE policy throws instead of
 * returning a fallback, so `prayer.polar.none` is policy-only in metadata.
 */
final class ThrowHandler implements PolarCircleHandler, IdentifiesCalculationSource
{
    /**
     * Registry id for this policy (never emitted as a source).
     */
    public const SOURCE_ID = 'prayer.polar.none';

    /**
     * Return the inputs unchanged (unsolvable dates throw downstream).
     *
     * @param Coordinates $coordinates Original observer location.
     * @param CivilDate $date Original civil date.
     * @param callable(Coordinates, CivilDate): bool $isSolvable Astro-backed probe (unused).
     * @return array{coordinates: Coordinates, date: CivilDate} Effective inputs to compute with.
     */
    public function resolve(Coordinates $coordinates, CivilDate $date, callable $isSolvable): array
    {
        return ['coordinates' => $coordinates, 'date' => $date];
    }

    /**
     * Stable provenance identity for this policy.
     *
     * @return CalculationSource Algorithm source carrying SOURCE_ID.
     */
    public function calculationSource(): CalculationSource
    {
        return CalculationSource::algorithm(self::SOURCE_ID);
    }
}
