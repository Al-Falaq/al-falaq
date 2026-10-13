<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\IshaRule;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\Traits\ValidatesFinite;

/**
 * Isha as a solar-depression angle below the horizon (most methods).
 */
final class AngleIsha implements IshaRule
{
    use ValidatesFinite;

    /**
     * Angle rule (angle lives on the instance, interval stays null).
     *
     * @param float $angle Depression angle in degrees (e.g. 18.0).
     */
    public function __construct(public readonly float $angle) {}

    /**
     * Angle-based: no interval.
     *
     * @return int|null Always null.
     */
    public function minutesAfterMaghrib(): ?int
    {
        return null;
    }

    /**
     * The configured depression angle.
     *
     * @return float Angle in degrees.
     */
    public function angle(): float
    {
        return $this->angle;
    }

    /**
     * Validate the angle variant (finite in [0, 30]).
     *
     * @return void Throws on invalid configuration.
     */
    public function validate(): void
    {
        $angle = self::assertFinite($this->angle, 'ishaAngle');

        if ($angle < 0.0 || $angle > 30.0) {
            throw new ValidationException(sprintf('Invalid ishaAngle %.4f: expected finite in [0, 30].', $angle));
        }
    }
}
