<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\Services;

/** @internal */
final class DeltaTProvider
{
    public static function forYear(float $year): float
    {
        throw new \LogicException('not implemented: M1 DeltaT pending Appendix B');
    }
}
