<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

/**
 * High-latitude correction result: Fajr/Isha night fractions (M1).
 */
final readonly class NightPortions
{
    /**
     * Correction result pair.
     *
     * @param float $fajrPortion Fraction of night before Fajr.
     * @param float $ishaPortion Fraction of night after Isha.
     */
    public function __construct(
        public float $fajrPortion,
        public float $ishaPortion,
    ) {}
}
