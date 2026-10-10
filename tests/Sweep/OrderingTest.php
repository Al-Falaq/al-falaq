<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Sweep;

use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Angle;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('sweep')]
final class OrderingTest extends TestCase
{
    public function testPreservesOrderingInvariantWithEmptyAdjustments(): void
    {
        mt_srand(1234);

        for ($i = 0; $i < 200; $i++) {
            $angle = mt_rand(-72000, 72000) / 100.0;
            $once = Angle::fromDegrees($angle)->normalized()->degrees;
            $twice = Angle::fromDegrees($once)->normalized()->degrees;
            self::assertSame($once, $twice);

            $date = CivilDate::fromYmd(2020, 1, 1)->plusDays(mt_rand(0, 366));
            self::assertTrue($date->plusDays(1)->plusDays(-1)->equals($date));
        }
    }
}
