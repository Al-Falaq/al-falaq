<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Tests\Goldens;

final class Comparator
{
    public const REF_TOLERANCE_DEG = 1e-9;

    public const CROSS_PLATFORM_TOLERANCE_DEG = 1e-7;

    public static function minutesEqual(\DateTimeImmutable $actualUtc, \DateTimeImmutable $expectedUtc): bool
    {
        return $actualUtc->format('Y-m-d H:i') === $expectedUtc->format('Y-m-d H:i');
    }

    public static function floatsEqual(float $actual, float $expected, bool $isReferenceLeg = true): bool
    {
        $tol = $isReferenceLeg ? self::REF_TOLERANCE_DEG : self::CROSS_PLATFORM_TOLERANCE_DEG;

        return abs($actual - $expected) <= $tol;
    }
}
