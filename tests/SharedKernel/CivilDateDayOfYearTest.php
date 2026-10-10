<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use PHPUnit\Framework\TestCase;

/**
 * Day-of-year for seasonal models (1–366).
 */
final class CivilDateDayOfYearTest extends TestCase
{
    public function testDayOfYear(): void
    {
        self::assertSame(1, CivilDate::fromYmd(2025, 1, 1)->dayOfYear());
        self::assertSame(365, CivilDate::fromYmd(2025, 12, 31)->dayOfYear());
        self::assertSame(366, CivilDate::fromYmd(2024, 12, 31)->dayOfYear());
        self::assertSame(60, CivilDate::fromYmd(2024, 2, 29)->dayOfYear());
    }
}
