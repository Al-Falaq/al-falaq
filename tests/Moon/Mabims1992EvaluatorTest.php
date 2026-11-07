<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Tests\Moon;

use AlFalaq\AlFalaq\Moon\Strategies\Mabims1992Evaluator;
use AlFalaq\AlFalaq\Moon\ValueObjects\CrescentGeometry;
use PHPUnit\Framework\TestCase;

/**
 * MABIMS-1992 criterion: (alt >= 2deg and elong >= 3deg) or age-at-moonset >= 8h.
 */
final class Mabims1992EvaluatorTest extends TestCase
{
    /**
     * Build geometry for the alt/elongation-only path (null age).
     *
     * @param float $alt Topocentric moon altitude in degrees.
     * @param float $elong Sun-moon elongation in degrees.
     * @return CrescentGeometry Valid bundle (no-moonset window).
     */
    private function geometryNoAge(float $alt, float $elong): CrescentGeometry
    {
        return new CrescentGeometry(
            -0.8333, // sunAltitude
            $alt, // moonAltitude
            $elong, // elongationDeg
            0.0, // moonAgeHours
            null, // lagMinutes
            0.0, // deltaAzimuthDeg
            0.5, // illuminatedFraction
            0.0, // arcvGeoDeg
            0.0, // widthArcmin
            null, // ageAtMoonsetHours
        );
    }

    /**
     * Build geometry with an age-at-moonset value.
     *
     * @param float $alt Topocentric moon altitude in degrees.
     * @param float $elong Sun-moon elongation in degrees.
     * @param float $age Age at moonset in hours.
     * @return CrescentGeometry Valid bundle.
     */
    private function geometryWithAge(float $alt, float $elong, float $age): CrescentGeometry
    {
        return new CrescentGeometry(
            -0.8333, // sunAltitude
            $alt, // moonAltitude
            $elong, // elongationDeg
            0.0, // moonAgeHours
            60.0, // lagMinutes
            0.0, // deltaAzimuthDeg
            0.5, // illuminatedFraction
            0.0, // arcvGeoDeg
            0.0, // widthArcmin
            $age, // ageAtMoonsetHours
        );
    }

    public function testVisibleWhenAltAboveTwoAndElongAboveThreeNoAge(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometryNoAge(3.0, 5.0));

        self::assertSame('mabims1992', $verdict->criterion);
        self::assertTrue($verdict->visible);
        self::assertSame('visible', $verdict->zone);
    }

    public function testNotVisibleWhenAltBelowTwoNoAge(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometryNoAge(1.9, 5.0));

        self::assertFalse($verdict->visible);
        self::assertSame('not-visible', $verdict->zone);
    }

    public function testNotVisibleWhenElongBelowThreeNoAge(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometryNoAge(3.0, 2.9));

        self::assertFalse($verdict->visible);
        self::assertSame('not-visible', $verdict->zone);
    }

    public function testVisibleViaAgeEightEvenWithLowAltAndElong(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometryWithAge(0.0, 0.0, 8.0));

        self::assertTrue($verdict->visible);
        self::assertSame('visible', $verdict->zone);
    }

    public function testNotVisibleBelowAgeEightWithLowAltAndElong(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometryWithAge(0.0, 0.0, 7.9));

        self::assertFalse($verdict->visible);
        self::assertSame('not-visible', $verdict->zone);
    }

    public function testBoundaryAgeExactlyEightIsVisible(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometryWithAge(0.0, 0.0, 8.0));

        self::assertTrue($verdict->visible);
    }

    public function testBoundaryAltExactlyTwoAndElongExactlyThreeIsVisible(): void
    {
        $verdict = (new Mabims1992Evaluator())->evaluate($this->geometryNoAge(2.0, 3.0));

        self::assertTrue($verdict->visible);
    }
}
