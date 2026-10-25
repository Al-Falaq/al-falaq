<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

/**
 * The Kaaba position: the single owner of the cross-context coordinate fact.
 *
 * Two bounded contexts need it — Qibla aims at it, and the Prayer polar
 * fallback substitutes it for an undefined location. Owning it in
 * SharedKernel keeps the two contexts siblings (no Prayer → Qibla edge)
 * and makes drift impossible: the Qibla contract constants reference these
 * values, so the literals exist in exactly one place.
 */
final class Kaaba
{
    /**
     * Kaaba latitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const LATITUDE = 21.4225241;

    /**
     * Kaaba longitude in degrees (pin Constants.py:8; goldens pin these digits).
     */
    public const LONGITUDE = 39.8261818;

    private function __construct() {}

    /**
     * The Kaaba position as a validated coordinates value object.
     *
     * @return Coordinates The Kaaba coordinates (WGS84 degrees).
     */
    public static function coordinates(): Coordinates
    {
        return Coordinates::fromDegrees(self::LATITUDE, self::LONGITUDE);
    }
}
