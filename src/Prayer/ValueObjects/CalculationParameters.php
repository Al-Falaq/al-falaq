<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

use AlFalaq\AlFalak\Prayer\Contracts\IshaRule;
use AlFalaq\AlFalak\Prayer\Contracts\MaghribRule;
use AlFalaq\AlFalak\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalak\Prayer\Enums\Madhab;
use AlFalaq\AlFalak\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalak\SharedKernel\Concerns\HasWithersGuard;
use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

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
