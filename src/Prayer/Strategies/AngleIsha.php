<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Strategies;

use AlFalaq\AlFalaq\Prayer\Contracts\IshaRule;
use AlFalaq\AlFalaq\SharedKernel\Contracts\IdentifiesCalculationSource;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\Traits\ValidatesFinite;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CalculationSource;

/**
 * Isha as a solar-depression angle below the horizon (most methods).
 */
final class AngleIsha implements IshaRule, IdentifiesCalculationSource
{
    use ValidatesFinite;

    /**
     * Registry id for this rule.
     */
    public const SOURCE_ID = 'prayer.isha.angle';

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
