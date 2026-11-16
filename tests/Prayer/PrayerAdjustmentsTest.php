<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Prayer;

use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerAdjustments;
use PHPUnit\Framework\TestCase;

/**
 * Adjustment serializer: every prayer key, enum order, unset reads zero.
 */
final class PrayerAdjustmentsTest extends TestCase
{
    /**
     * @return list<string>
     */
    private static function prayerKeys(): array
    {
        return array_map(
            static fn(Prayer $prayer): string => $prayer->value,
            Prayer::cases(),
        );
    }

    public function testEmptySerializesEveryPrayerKeyAsZero(): void
    {
        $array = PrayerAdjustments::empty()->toArray();

        self::assertSame(self::prayerKeys(), array_keys($array));
        self::assertSame([], array_filter($array, static fn(int $minutes): bool => $minutes !== 0));
    }

    public function testSerializesSetOffsetsAndFillsMissingPrayers(): void
    {
        $array = PrayerAdjustments::empty()
            ->with(Prayer::IMSAK, -5)
            ->with(Prayer::ISHA, 7)
            ->toArray();

        self::assertSame(-5, $array['imsak']);
        self::assertSame(7, $array['isha']);

        foreach (self::prayerKeys() as $key) {
            if ($key !== 'imsak' && $key !== 'isha') {
                self::assertSame(0, $array[$key], sprintf('%s must default to 0.', $key));
            }
        }
    }

    public function testKeyOrderIsEnumOrderNotInsertionOrder(): void
    {
        $array = PrayerAdjustments::empty()
            ->with(Prayer::ISHA, 1)
            ->with(Prayer::IMSAK, 1)
            ->toArray();

        self::assertSame(self::prayerKeys(), array_keys($array));
    }
}
