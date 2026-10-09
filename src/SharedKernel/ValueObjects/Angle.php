<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Traits\ValidatesFinite;

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
