<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\ValueObjects;

use AlFalaq\AlFalaq\Prayer\Contracts\IshaRule;
use AlFalaq\AlFalaq\Prayer\Contracts\MaghribRule;
use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\HighLatitudeRule;
use AlFalaq\AlFalaq\Prayer\Enums\Madhab;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\SharedKernel\Traits\HasWithersGuard;
use AlFalaq\AlFalaq\SharedKernel\Traits\ValidatesFinite;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ConfigurationException;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Immutable prayer configuration: method defaults plus caller overrides.
 *
 * Build via CalculationMethod::X->defaultParameters(), then with*().
 * Effective per-prayer offset = methodAdjustments + adjustments.
 */
final readonly class CalculationParameters
{
    use HasWithersGuard;
    use ValidatesFinite;

    private function __construct(
        public CalculationMethod $method,
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
     * Validated construction for presets and NONE-method callers alike.
     *
     * @param CalculationMethod $method Owning method (NONE for custom configs).
     * @param Madhab $madhab Asr school.
     * @param HighLatitudeRule $highLatitudeRule Night-splitting rule.
     * @param PolarCircleRule $polarCircleRule Polar fallback selector.
     * @param mixed $fajrAngle Finite float in [0, 30].
     * @param IshaRule $ishaRule Angle-based (angle in [0, 30]) or interval (minutes >= 1).
     * @param MaghribRule $maghribRule Angle (in [0, 30]) or minutes after sunset (>= 0).
     * @param mixed $dhuhrOffsetMinutes Int in [0, 5]; bools reject.
     * @param mixed $imsakOffsetMinutes Int in [0, 60]; bools reject.
     * @param mixed $ishraqOffsetMinutes Int in [0, 60]; bools reject.
     * @param mixed $dhuhaOffsetMinutes Int in [0, 60], >= ishraq; bools reject.
     * @param mixed $elevationM Finite float in [0, 8848]; bools reject.
     * @param bool $isRamadan Umm al-Qura Ramadan mode (rejected here unless UMM_AL_QURA).
     * @param PrayerAdjustments $methodAdjustments Shipped per-method offsets.
     * @param PrayerAdjustments $adjustments Caller overrides.
     * @return self Validated parameters.
     */
    public static function create(
        CalculationMethod $method,
        Madhab $madhab,
        HighLatitudeRule $highLatitudeRule,
        PolarCircleRule $polarCircleRule,
        mixed $fajrAngle,
        IshaRule $ishaRule,
        MaghribRule $maghribRule,
        mixed $dhuhrOffsetMinutes,
        mixed $imsakOffsetMinutes,
        mixed $ishraqOffsetMinutes,
        mixed $dhuhaOffsetMinutes,
        mixed $elevationM,
        bool $isRamadan,
        PrayerAdjustments $methodAdjustments,
        PrayerAdjustments $adjustments,
    ): self {
        $fajr = self::validateAngle($fajrAngle, 'fajrAngle');

        $ishaRule->validate();
        $maghribRule->validate();
        self::assertRamadanCompatible($method, $isRamadan);

        $dhuhrOff = self::guardOffsetMinutes($dhuhrOffsetMinutes, 'dhuhrOffsetMinutes', 0, 5);
        $imsakOff = self::guardOffsetMinutes($imsakOffsetMinutes, 'imsakOffsetMinutes');
        $ishraqOff = self::guardOffsetMinutes($ishraqOffsetMinutes, 'ishraqOffsetMinutes');
        $dhuhaOff = self::guardOffsetMinutes($dhuhaOffsetMinutes, 'dhuhaOffsetMinutes');

        if ($dhuhaOff < $ishraqOff) {
            throw new ValidationException(sprintf(
                'Invalid dhuhaOffsetMinutes %d: must be >= ishraqOffsetMinutes %d.',
                $dhuhaOff,
                $ishraqOff,
            ));
        }

        return new self(
            $method,
            $madhab,
            $highLatitudeRule,
            $polarCircleRule,
            $fajr,
            $ishaRule,
            $maghribRule,
            $dhuhrOff,
            $imsakOff,
            $ishraqOff,
            $dhuhaOff,
            self::validateElevation($elevationM),
            $isRamadan,
            $methodAdjustments,
            $adjustments,
        );
    }

    /**
     * Copy with selected fields replaced (the only wither construction site).
     *
     * Every field is non-nullable, so `null` is a safe "keep current"
     * sentinel. If a nullable field is ever added, switch that field to an
     * explicit sentinel rather than overloading null.
     *
     * @param Madhab|null $madhab Replacement Asr school, null keeps current.
     * @param HighLatitudeRule|null $highLatitudeRule Replacement rule, null keeps current.
     * @param PolarCircleRule|null $polarCircleRule Replacement selector, null keeps current.
     * @param float|null $fajrAngle Replacement angle, null keeps current.
     * @param IshaRule|null $ishaRule Replacement rule, null keeps current.
     * @param MaghribRule|null $maghribRule Replacement rule, null keeps current.
     * @param int|null $dhuhrOffsetMinutes Replacement offset, null keeps current.
     * @param int|null $imsakOffsetMinutes Replacement offset, null keeps current.
     * @param int|null $ishraqOffsetMinutes Replacement offset, null keeps current.
     * @param int|null $dhuhaOffsetMinutes Replacement offset, null keeps current.
     * @param float|null $elevationM Replacement elevation, null keeps current.
     * @param bool|null $isRamadan Replacement flag, null keeps current.
     * @param PrayerAdjustments|null $methodAdjustments Replacement layer, null keeps current.
     * @param PrayerAdjustments|null $adjustments Replacement layer, null keeps current.
     * @return self New parameters; this instance is unchanged.
     */
    private function copyWith(
        ?Madhab $madhab = null,
        ?HighLatitudeRule $highLatitudeRule = null,
        ?PolarCircleRule $polarCircleRule = null,
        ?float $fajrAngle = null,
        ?IshaRule $ishaRule = null,
        ?MaghribRule $maghribRule = null,
        ?int $dhuhrOffsetMinutes = null,
        ?int $imsakOffsetMinutes = null,
        ?int $ishraqOffsetMinutes = null,
        ?int $dhuhaOffsetMinutes = null,
        ?float $elevationM = null,
        ?bool $isRamadan = null,
        ?PrayerAdjustments $methodAdjustments = null,
        ?PrayerAdjustments $adjustments = null,
    ): self {
        return new self(
            $this->method,
            $madhab ?? $this->madhab,
            $highLatitudeRule ?? $this->highLatitudeRule,
            $polarCircleRule ?? $this->polarCircleRule,
            $fajrAngle ?? $this->fajrAngle,
            $ishaRule ?? $this->ishaRule,
            $maghribRule ?? $this->maghribRule,
            $dhuhrOffsetMinutes ?? $this->dhuhrOffsetMinutes,
            $imsakOffsetMinutes ?? $this->imsakOffsetMinutes,
            $ishraqOffsetMinutes ?? $this->ishraqOffsetMinutes,
            $dhuhaOffsetMinutes ?? $this->dhuhaOffsetMinutes,
            $elevationM ?? $this->elevationM,
            $isRamadan ?? $this->isRamadan,
            $methodAdjustments ?? $this->methodAdjustments,
            $adjustments ?? $this->adjustments,
        );
    }

    /**
     * Guard Umm al-Qura Ramadan mode at construction time.
     *
     * @param CalculationMethod $method Owning method (NONE for custom configs).
     * @param bool $isRamadan Umm al-Qura Ramadan mode.
     * @return void
     */
    private static function assertRamadanCompatible(CalculationMethod $method, bool $isRamadan): void
    {
        if ($isRamadan && $method !== CalculationMethod::UMM_AL_QURA) {
            throw new ConfigurationException(sprintf(
                'isRamadan requires UMM_AL_QURA (got %s).',
                $method->value,
            ));
        }
    }

    /**
     * Validate a solar-depression angle is finite and in range.
     *
     * @param mixed $value Candidate angle in degrees.
     * @param string $name Field name for error messages.
     * @return float Validated angle.
     */
    private static function validateAngle(mixed $value, string $name): float
    {
        $angle = self::assertFinite($value, $name);

        if ($angle < 0.0 || $angle > 30.0) {
            throw new ValidationException(sprintf('Invalid %s %.4f: expected finite in [0, 30].', $name, $angle));
        }

        return $angle;
    }

    /**
     * Validate observer elevation is finite and in range.
     *
     * @param mixed $value Candidate elevation in meters.
     * @return float Validated elevation.
     */
    private static function validateElevation(mixed $value): float
    {
        if (is_bool($value) || ! is_int($value) && ! is_float($value)) {
            throw new ValidationException('Invalid elevationM: expected finite float >= 0.');
        }

        $elevation = (float) $value;

        if (! is_finite($elevation) || $elevation < 0.0 || $elevation > 8848.0) {
            throw new ValidationException(sprintf('Invalid elevationM %.2f: expected finite in [0, 8848].', $elevation));
        }

        return $elevation;
    }

    /**
     * Override the Asr school, keeping everything else.
     *
     * @param Madhab $madhab Replacement school.
     * @return self New parameters; this instance is unchanged.
     */
    public function withMadhab(Madhab $madhab): self
    {
        return $this->copyWith(madhab: $madhab);
    }

    /**
     * Override observer elevation (dip-of-horizon), 0–8848 m.
     *
     * @param mixed $meters Finite number in [0, 8848]; bools reject.
     * @return self New parameters; this instance is unchanged.
     */
    public function withElevationM(mixed $meters): self
    {
        $value = self::validateElevation($meters);

        return $this->copyWith(elevationM: $value);
    }

    /**
     * Override caller minute adjustments (added on top of method ones).
     *
     * @param PrayerAdjustments $adjustments Replacement caller adjustments.
     * @return self New parameters; this instance is unchanged.
     */
    public function withAdjustments(PrayerAdjustments $adjustments): self
    {
        return $this->copyWith(adjustments: $adjustments);
    }

    /**
     * Toggle Umm al-Qura Ramadan Isha mode (120 vs 90 min after Maghrib).
     *
     * Fails fast on non-Umm-al-Qura methods instead of at calculate() time.
     *
     * @param bool $isRamadan True during Ramadan (UQU only).
     * @return self New parameters; this instance is unchanged.
     */
    public function withRamadan(bool $isRamadan): self
    {
        self::assertRamadanCompatible($this->method, $isRamadan);

        return $this->copyWith(isRamadan: $isRamadan);
    }

    /**
     * Override the Fajr solar-depression angle, keeping everything else.
     *
     * @param mixed $angle Finite float in [0, 30].
     * @return self New parameters; this instance is unchanged.
     */
    public function withFajrAngle(mixed $angle): self
    {
        $value = self::validateAngle($angle, 'fajrAngle');

        return $this->copyWith(fajrAngle: $value);
    }

    /**
     * Override the Isha rule, keeping everything else.
     *
     * @param IshaRule $ishaRule Angle-based (angle in [0, 30]) or interval (minutes >= 1).
     * @return self New parameters; this instance is unchanged.
     */
    public function withIshaRule(IshaRule $ishaRule): self
    {
        $ishaRule->validate();

        return $this->copyWith(ishaRule: $ishaRule);
    }

    /**
     * Override the Maghrib rule, keeping everything else.
     *
     * @param MaghribRule $maghribRule Angle (in [0, 30]) or minutes after sunset (>= 0).
     * @return self New parameters; this instance is unchanged.
     */
    public function withMaghribRule(MaghribRule $maghribRule): self
    {
        $maghribRule->validate();

        return $this->copyWith(maghribRule: $maghribRule);
    }

    /**
     * Override the high-latitude night-splitting rule, keeping everything else.
     *
     * @param HighLatitudeRule $highLatitudeRule Replacement rule.
     * @return self New parameters; this instance is unchanged.
     */
    public function withHighLatitudeRule(HighLatitudeRule $highLatitudeRule): self
    {
        return $this->copyWith(highLatitudeRule: $highLatitudeRule);
    }

    /**
     * Override the polar-circle fallback selector, keeping everything else.
     *
     * @param PolarCircleRule $polarCircleRule Replacement selector.
     * @return self New parameters; this instance is unchanged.
     */
    public function withPolarCircleRule(PolarCircleRule $polarCircleRule): self
    {
        return $this->copyWith(polarCircleRule: $polarCircleRule);
    }

    /**
     * Override the Imsak offset minutes, keeping everything else.
     *
     * @param mixed $minutes Int in [0, 60]; bools reject.
     * @return self New parameters; this instance is unchanged.
     */
    public function withImsakOffset(mixed $minutes): self
    {
        $value = self::guardOffsetMinutes($minutes, 'imsakOffsetMinutes');

        return $this->copyWith(imsakOffsetMinutes: $value);
    }

    /**
     * Override the Ishraq offset minutes, keeping everything else.
     *
     * @param mixed $minutes Int in [0, 60]; bools reject; dhuha must stay >= new value.
     * @return self New parameters; this instance is unchanged.
     */
    public function withIshraqOffset(mixed $minutes): self
    {
        $value = self::guardOffsetMinutes($minutes, 'ishraqOffsetMinutes');

        if ($this->dhuhaOffsetMinutes < $value) {
            throw new ValidationException(sprintf(
                'Invalid dhuhaOffsetMinutes %d: must be >= ishraqOffsetMinutes %d.',
                $this->dhuhaOffsetMinutes,
                $value,
            ));
        }

        return $this->copyWith(ishraqOffsetMinutes: $value);
    }

    /**
     * Override the Dhuha offset minutes, keeping everything else.
     *
     * @param mixed $minutes Int in [0, 60], >= ishraq; bools reject.
     * @return self New parameters; this instance is unchanged.
     */
    public function withDhuhaOffset(mixed $minutes): self
    {
        $value = self::guardOffsetMinutes($minutes, 'dhuhaOffsetMinutes');

        if ($value < $this->ishraqOffsetMinutes) {
            throw new ValidationException(sprintf(
                'Invalid dhuhaOffsetMinutes %d: must be >= ishraqOffsetMinutes %d.',
                $value,
                $this->ishraqOffsetMinutes,
            ));
        }

        return $this->copyWith(dhuhaOffsetMinutes: $value);
    }

    /**
     * Override the Dhuhr offset minutes, keeping everything else.
     *
     * @param mixed $minutes Int in [0, 5]; bools reject.
     * @return self New parameters; this instance is unchanged.
     */
    public function withDhuhrOffset(mixed $minutes): self
    {
        $value = self::guardOffsetMinutes($minutes, 'dhuhrOffsetMinutes', 0, 5);

        return $this->copyWith(dhuhrOffsetMinutes: $value);
    }

    /**
     * Override the shipped per-method adjustments, keeping everything else.
     *
     * @param PrayerAdjustments $methodAdjustments Replacement method offsets.
     * @return self New parameters; this instance is unchanged.
     */
    public function withMethodAdjustments(PrayerAdjustments $methodAdjustments): self
    {
        return $this->copyWith(methodAdjustments: $methodAdjustments);
    }
}
