<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\MaghribRule;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\Traits\ValidatesFinite;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;

/**
 * Maghrib as a solar-depression angle (Tehran 4.5°).
 */
final class AngleMaghrib implements MaghribRule, IdentifiesCalculationSource
{
    use ValidatesFinite;

    /**
     * Registry id for this rule.
     */
    public const SOURCE_ID = 'prayer.maghrib.angle';

    /**
     * Angle rule (Tehran-style maghrib below sunset).
     *
     * @param float $angle Depression angle in degrees.
     */
    public function __construct(public readonly float $angle) {}

    /**
     * Angle-based: no interval.
     *
     * @return int|null Always null.
     */
    public function minutesAfterSunset(): ?int
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
        $angle = self::assertFinite($this->angle, 'maghribAngle');

        if ($angle < 0.0 || $angle > 30.0) {
            throw new ValidationException(sprintf('Invalid maghribAngle %.4f: expected finite in [0, 30].', $angle));
        }
    }

    /**
     * Stable provenance identity for this rule.
     *
     * @return CalculationSource Algorithm source carrying SOURCE_ID.
     */
    public function calculationSource(): CalculationSource
    {
        return CalculationSource::algorithm(self::SOURCE_ID);
    }
}
