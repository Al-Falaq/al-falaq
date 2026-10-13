<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Goldens;

final class Comparator
{
    public static function minutesEqual(\DateTimeImmutable $actualUtc, \DateTimeImmutable $expectedUtc): bool
    {
        return $actualUtc->format('Y-m-d H:i') === $expectedUtc->format('Y-m-d H:i');
    }
}
