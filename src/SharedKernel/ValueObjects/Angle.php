<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Traits\ValidatesFinite;

final readonly class Angle
{
    use ValidatesFinite;

    private function __construct(public float $degrees) {}

    public static function fromDegrees(mixed $degrees): self
    {
        return new self(self::assertFinite($degrees, 'angle'));
    }

    public function normalized(): self
    {
        $unwound = fmod($this->degrees, 360.0);

        if ($unwound < 0.0) {
            $unwound += 360.0;
        }

        // fmod(360,360) === 0 already in range; guard -0.0.
        return new self($unwound + 0.0);
    }

    public function toRadians(): float
    {
        return $this->degrees * M_PI / 180.0;
    }
}
