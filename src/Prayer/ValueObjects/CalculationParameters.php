<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

use AlFalaq\AlFalak\Prayer\Contracts\IshaRule;
use AlFalaq\AlFalak\Prayer\Contracts\MaghribRule;
use AlFalaq\AlFalak\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalak\Prayer\Enums\Madhab;
use AlFalaq\AlFalak\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalak\SharedKernel\Traits\HasWithersGuard;
use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

/**
 * Immutable prayer configuration: method defaults plus caller overrides.
 *
 * Build via CalculationMethod::X->defaultParameters(), then with*().
 * Effective per-prayer offset = methodAdjustments + adjustments.
 */
final readonly class CalculationParameters
{
    use HasWithersGuard;

    private function __construct(
        public Madhab $madhab,
        public HighLatitudeRule $highLatitudeRule,
        public PolarCircleRule $polarCircleRule,
        public float $fajrAngle,
        public IshaRule $ishaRule,
        public MaghribRule $maghribRule,
        public int $dhuhrOffsetMinutes,
        public int $imsakOffsetMinutes,
        public int $ishraqOffsetMinutes,
        public int $dhuhaOffsetMinutes,
        public float $elevationM,
        public bool $isRamadan,
        public PrayerAdjustments $methodAdjustments,
        public PrayerAdjustments $adjustments,
    ) {}

    /**
     * Override the Asr school, keeping everything else.
     *
     * @param Madhab $madhab Replacement school.
     * @return self New parameters; this instance is unchanged.
     */
    public function withMadhab(Madhab $madhab): self
    {
        return new self(
            $madhab,
            $this->highLatitudeRule,
            $this->polarCircleRule,
            $this->fajrAngle,
            $this->ishaRule,
            $this->maghribRule,
            $this->dhuhrOffsetMinutes,
            $this->imsakOffsetMinutes,
            $this->ishraqOffsetMinutes,
            $this->dhuhaOffsetMinutes,
            $this->elevationM,
            $this->isRamadan,
            $this->methodAdjustments,
            $this->adjustments,
        );
    }

    /**
     * Override observer elevation (dip-of-horizon), 0–8848 m.
     *
     * @param mixed $meters Finite number in [0, 8848]; bools reject.
     * @return self New parameters; this instance is unchanged.
     */
    public function withElevationM(mixed $meters): self
    {
        if (is_bool($meters) || ! is_int($meters) && ! is_float($meters)) {
            throw new ValidationException('Invalid elevationM: expected finite float >= 0.');
        }

        $value = (float) $meters;

        if (! is_finite($value) || $value < 0.0 || $value > 8848.0) {
            throw new ValidationException(sprintf('Invalid elevationM %.2f: expected finite in [0, 8848].', $value));
        }

        return new self(
            $this->madhab,
            $this->highLatitudeRule,
            $this->polarCircleRule,
            $this->fajrAngle,
            $this->ishaRule,
            $this->maghribRule,
            $this->dhuhrOffsetMinutes,
            $this->imsakOffsetMinutes,
            $this->ishraqOffsetMinutes,
            $this->dhuhaOffsetMinutes,
            $value,
            $this->isRamadan,
            $this->methodAdjustments,
            $this->adjustments,
        );
    }

    /**
     * Override caller minute adjustments (added on top of method ones).
     *
     * @param PrayerAdjustments $adjustments Replacement caller adjustments.
     * @return self New parameters; this instance is unchanged.
     */
    public function withAdjustments(PrayerAdjustments $adjustments): self
    {
        return new self(
            $this->madhab,
            $this->highLatitudeRule,
            $this->polarCircleRule,
            $this->fajrAngle,
            $this->ishaRule,
            $this->maghribRule,
            $this->dhuhrOffsetMinutes,
            $this->imsakOffsetMinutes,
            $this->ishraqOffsetMinutes,
            $this->dhuhaOffsetMinutes,
            $this->elevationM,
            $this->isRamadan,
            $this->methodAdjustments,
            $adjustments,
        );
    }

    /**
     * Toggle Umm al-Qura Ramadan Isha mode (120 vs 90 min after Maghrib).
     *
     * @param bool $isRamadan True during Ramadan (UQU only; else throws later).
     * @return self New parameters; this instance is unchanged.
     */
    public function withRamadan(bool $isRamadan): self
    {
        return new self(
            $this->madhab,
            $this->highLatitudeRule,
            $this->polarCircleRule,
            $this->fajrAngle,
            $this->ishaRule,
            $this->maghribRule,
            $this->dhuhrOffsetMinutes,
            $this->imsakOffsetMinutes,
            $this->ishraqOffsetMinutes,
            $this->dhuhaOffsetMinutes,
            $this->elevationM,
            $isRamadan,
            $this->methodAdjustments,
            $this->adjustments,
        );
    }
}
