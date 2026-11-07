<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Astro;

use AlFalaq\AlFalaq\Astro\ValueObjects\SolarTime;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Minutes;
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

    public function testNormalizesAcceptedZeroOffsetZonesToNamedUtc(): void
    {
        $instants = [
            new \DateTimeImmutable('@1750000000'),
            new \DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('+00:00')),
            new \DateTimeImmutable('2025-03-29 23:30:00', new \DateTimeZone('Europe/London')),
        ];

        foreach ($instants as $instant) {
            $solar = $this->triple($instant);

            self::assertSame('UTC', $solar->transit->getTimezone()->getName());
            self::assertSame('UTC', $solar->sunrise->getTimezone()->getName());
            self::assertSame('UTC', $solar->sunset->getTimezone()->getName());
        }
    }

    public function testNormalizationKeepsIntervalMathInUtcAcrossDst(): void
    {
        // Europe/London is at offset 0 on 2025-03-29 but crosses into BST
        // the next morning; adding Isha's interval must not change the offset.
        $sunset = new \DateTimeImmutable('2025-03-29 23:30:00', new \DateTimeZone('Europe/London'));

        $isha = Minutes::add($this->triple($sunset)->sunset, 120);

        self::assertSame(0, $isha->getOffset());
        self::assertSame('UTC', $isha->getTimezone()->getName());
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
