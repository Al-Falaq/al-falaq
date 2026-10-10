<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Seasonal twilight minutes transcribed from pin Twilight.py.
 *
 * Vectors are oracle minute-offsets (author-verified 2026-10-10).
 */
final class SeasonAdjustedTwilightTest extends TestCase
{
    public function testSeasonalMinutesMatchPin(): void
    {
        $twilight = new SeasonAdjustedTwilight();

        self::assertSame(['morning' => 103, 'evening' => 96], $twilight->seasonalMinutes(15, 2025, 59.9139));
        self::assertSame(['morning' => 127, 'evening' => 81], $twilight->seasonalMinutes(172, 2025, 59.9139));
        self::assertSame(['morning' => 99, 'evening' => 74], $twilight->seasonalMinutes(15, 2025, -33.8688));
        self::assertSame(['morning' => 101, 'evening' => 94], $twilight->seasonalMinutes(15, 2025, 55.0));
    }

    public function testSeasonalMinutesRejectsBadInput(): void
    {
        $this->expectException(ValidationException::class);
        (new SeasonAdjustedTwilight())->seasonalMinutes(0, 2025, 59.9139);
    }
}
