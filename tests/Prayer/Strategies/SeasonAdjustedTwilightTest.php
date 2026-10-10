<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Seasonal twilight offsets transcribed from pin Twilight.py.
 *
 * Vectors are oracle second-offsets, verified by executing the pin directly
 * (season_adjusted_morning/evening_twilight minus/plus the base instant).
 */
final class SeasonAdjustedTwilightTest extends TestCase
{
    public function testSeasonalSecondsMatchPin(): void
    {
        $twilight = new SeasonAdjustedTwilight();

        self::assertSame(['morning' => 6207, 'evening' => 5750], $twilight->seasonalSeconds(15, 2025, 59.9139));
        self::assertSame(['morning' => 7622, 'evening' => 4880], $twilight->seasonalSeconds(172, 2025, 59.9139));
        self::assertSame(['morning' => 5969, 'evening' => 4419], $twilight->seasonalSeconds(15, 2025, -33.8688));
        self::assertSame(['morning' => 6067, 'evening' => 5648], $twilight->seasonalSeconds(15, 2025, 55.0));
    }

    public function testSeasonalSecondsRejectsBadInput(): void
    {
        $this->expectException(ValidationException::class);
        (new SeasonAdjustedTwilight())->seasonalSeconds(0, 2025, 59.9139);
    }
}
