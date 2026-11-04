<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * SolarTime UTC-anchor validation: any zero-offset zone is UTC; others reject.
 */
final class SolarTimeTest extends TestCase
{
    private function triple(\DateTimeImmutable $instant): SolarTime
    {
        return new SolarTime($instant, $instant, $instant);
    }

    public function testAcceptsNamedUtcZone(): void
    {
        $instant = new \DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('UTC'));

        self::assertInstanceOf(SolarTime::class, $this->triple($instant));
    }

    public function testAcceptsEpochInstant(): void
    {
        // @-epoch instants are UTC but their zone reports '+00:00', not 'UTC'
        // (the old name check falsely rejected them).
        $instant = new \DateTimeImmutable('@1750000000');

        self::assertSame('+00:00', $instant->getTimezone()->getName());
        self::assertInstanceOf(SolarTime::class, $this->triple($instant));
    }

    public function testAcceptsPlusZeroOffsetZone(): void
    {
        $instant = new \DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('+00:00'));

        self::assertInstanceOf(SolarTime::class, $this->triple($instant));
    }

    public function testRejectsNonZeroOffset(): void
    {
        $instant = new \DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('+01:00'));

        try {
            $this->triple($instant);

            self::fail('expected ValidationException for a +01:00 instant.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('must be UTC', $e->getMessage());
        }
    }
}
