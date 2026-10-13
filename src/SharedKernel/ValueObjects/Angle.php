<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Traits\ValidatesFinite;

/**
 * A validated angle in degrees (any input range; normalize when needed).
 */
final readonly class Angle
{
    use ValidatesFinite;

    private function __construct(public float $degrees) {}

    /**
     * Build from degrees, rejecting bools and non-finite values.
     *
     * @param mixed $degrees Int or float; anything else throws.
     * @return self Un-normalized; call normalized() for [0, 360).
     */
    public static function fromDegrees(mixed $degrees): self
    {
        return new self(self::assertFinite($degrees, 'angle'));
    }

    /**
     * Unwind into [0, 360) idempotently (fmod + negative correction).
     *
     * @return self Normalized copy; calling twice is a no-op.
     */
    public function normalized(): self
    {
        $unwound = fmod($this->degrees, 360.0);

        if ($unwound < 0.0) {
            $unwound += 360.0;
        }

        // Not cosmetic: ulp(360) ≈ 5.7e-14, so a value a few ulps below zero
        // (e.g. fmod(-1.2e-14, 360) + 360 for a point micrometres east of
        // Makkah's meridian) rounds to exactly 360.0, which range checks
        // reject. Re-clamp keeps the documented [0, 360) range.
        if ($unwound >= 360.0) {
            $unwound = 0.0;
        }

        // fmod(360,360) === 0 already in range; guard -0.0.
        return new self($unwound + 0.0);
    }

    /**
     * Convert to radians for trig.
     *
     * @return float Degrees × π / 180.
     */
    public function toRadians(): float
    {
        return $this->degrees * M_PI / 180.0;
    }
}
