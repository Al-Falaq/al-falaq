<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Services;

use AlFalaq\AlFalaq\Prayer\Traits\NormalizesCoordinatesInput;
use AlFalaq\AlFalaq\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalaq\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalaq\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalaq\Prayer\Contracts\TwilightModel;
use AlFalaq\AlFalaq\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalaq\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * Single-day prayer-time pipeline; strategies injected, no computation in ctor.
 *
 * M0 skeleton: calculate() throws until the M1 Appendix A freeze.
 */
final class PrayerTimesCalculator implements PrayerTimesCalculatorInterface
{
    use NormalizesCoordinatesInput;

    /**
     * Wire the three behavioral strategies explicitly (no container).
     *
     * @param HighLatitudeCorrection $highLatitudeCorrection Night splitting above ~48° latitude.
     * @param PolarCircleHandler $polarCircleHandler Polar day/night fallback.
     * @param TwilightModel $twilightModel Seasonal angles for MSC method.
     */
    public function __construct(
        private readonly HighLatitudeCorrection $highLatitudeCorrection,
        private readonly PolarCircleHandler $polarCircleHandler,
        private readonly TwilightModel $twilightModel,
    ) {}

    /**
     * Expose the injected high-latitude strategy (introspection/testing).
     *
     * @return HighLatitudeCorrection The wired correction.
     */
    public function highLatitudeCorrection(): HighLatitudeCorrection
    {
        return $this->highLatitudeCorrection;
    }

    /**
     * Expose the injected polar-circle strategy (introspection/testing).
     *
     * @return PolarCircleHandler The wired handler.
     */
    public function polarCircleHandler(): PolarCircleHandler
    {
        return $this->polarCircleHandler;
    }

    /**
     * Expose the injected twilight strategy (introspection/testing).
     *
     * @return TwilightModel The wired model.
     */
    public function twilightModel(): TwilightModel
    {
        return $this->twilightModel;
    }

    /**
     * Recommended wiring: middle-of-night + nearest-latitude + seasonal twilight.
     *
     * @return self Ready calculator covering 95% of use cases.
     */
    public static function default(): self
    {
        return new self(
            new \AlFalaq\AlFalaq\Prayer\Strategies\MiddleOfNightCorrection(),
            new \AlFalaq\AlFalaq\Prayer\Strategies\NearestLatitudeHandler(),
            new \AlFalaq\AlFalaq\Prayer\Strategies\SeasonAdjustedTwilight(),
        );
    }

    /**
     * Method-aware wiring entry point (M0 stub: currently returns default()).
     *
     * M1 will select strategies from $parameters; the argument is reserved and intentionally unused until then.
     *
     * @param CalculationParameters $parameters Resolved method parameters (reserved for M1, currently ignored).
     * @return self Default calculator.
     */
    public static function defaultFor(CalculationParameters $parameters): self
    {
        return self::default();
    }

    /**
     * Compute all ten prayer instants for one date and place (UTC).
     *
     * @param Coordinates|array{0: mixed, 1: mixed} $coordinates VO or [lat, lon] pair.
     * @param CivilDate $date Civil date to compute.
     * @param CalculationParameters $parameters Method defaults + overrides.
     * @return PrayerSchedule All instants in UTC; display via forZone().
     */
    public function calculate(Coordinates|array $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
    {
        self::normalizeCoordinates($coordinates);

        throw new \LogicException('not implemented: M1 prayer pipeline pending Appendix A goldens');
    }
}
