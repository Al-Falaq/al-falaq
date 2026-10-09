<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

final readonly class NightPortions
{
    public function __construct(
        public float $fajrPortion,
        public float $ishaPortion,
    ) {}
}
