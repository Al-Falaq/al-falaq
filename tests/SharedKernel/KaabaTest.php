<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\SharedKernel;

use AlFalaq\AlFalaq\Prayer\Strategies\MakkahHandler;
use AlFalaq\AlFalaq\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Kaaba;
use PHPUnit\Framework\TestCase;

/**
 * The Kaaba coordinates are a cross-context domain fact: Qibla aims at
 * them, the Prayer polar fallback substitutes them. One owner, no drift.
 */
final class KaabaTest extends TestCase
{
    public function testCoordinatesAreThePinnedKaabaPosition(): void
    {
        self::assertSame(21.4225241, Kaaba::LATITUDE);
        self::assertSame(39.8261818, Kaaba::LONGITUDE);
    }

    public function testCoordinatesFactoryMatchesTheConstants(): void
    {
        $coordinates = Kaaba::coordinates();

        self::assertSame(Kaaba::LATITUDE, $coordinates->latitude);
        self::assertSame(Kaaba::LONGITUDE, $coordinates->longitude);
    }

    /**
     * The public Qibla constants keep their pinned values.
     *
     * GeodesyCalculator::MAKKAH_LATITUDE is documented public API; the
     * shared owner must not move it.
     */
    public function testQiblaConstantsStillHoldThePinnedValues(): void
    {
        self::assertSame(21.4225241, GeodesyCalculator::MAKKAH_LATITUDE);
        self::assertSame(39.8261818, GeodesyCalculator::MAKKAH_LONGITUDE);
    }

    /**
     * Drift guard: the polar fallback must resolve to the shared owner.
     */
    public function testMakkahHandlerResolvesToTheSharedKaabaCoordinates(): void
    {
        $resolved = (new MakkahHandler())->resolve(
            Coordinates::fromDegrees(60.0, 10.0),
            CivilDate::fromYmd(2025, 6, 21),
            static fn(): bool => true,
        );

        self::assertTrue($resolved['coordinates']->equals(Kaaba::coordinates()));
    }
}
