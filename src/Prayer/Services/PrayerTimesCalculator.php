<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Services;

use AlFalaq\AlFalak\Prayer\Concerns\NormalizesCoordinatesInput;
use AlFalaq\AlFalak\Prayer\Contracts\HighLatitudeCorrection;
use AlFalaq\AlFalak\Prayer\Contracts\PolarCircleHandler;
use AlFalaq\AlFalak\Prayer\Contracts\PrayerTimesCalculatorInterface;
use AlFalaq\AlFalak\Prayer\Contracts\TwilightModel;
use AlFalaq\AlFalak\Prayer\ValueObjects\CalculationParameters;
use AlFalaq\AlFalak\Prayer\ValueObjects\PrayerSchedule;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class PrayerTimesCalculator implements PrayerTimesCalculatorInterface
{
    use NormalizesCoordinatesInput;

    public function __construct(
        private readonly HighLatitudeCorrection $highLatitudeCorrection,
        private readonly PolarCircleHandler $polarCircleHandler,
        private readonly TwilightModel $twilightModel,
    ) {}

    public function highLatitudeCorrection(): HighLatitudeCorrection
    {
        return $this->highLatitudeCorrection;
    }

    public function polarCircleHandler(): PolarCircleHandler
    {
        return $this->polarCircleHandler;
    }

    public function twilightModel(): TwilightModel
    {
        return $this->twilightModel;
    }

    public static function default(): self
    {
        return new self(
            new \AlFalaq\AlFalak\Prayer\Strategies\MiddleOfNightCorrection(),
            new \AlFalaq\AlFalak\Prayer\Strategies\NearestLatitudeHandler(),
            new \AlFalaq\AlFalak\Prayer\Strategies\SeasonAdjustedTwilight(),
        );
    }

    public static function defaultFor(CalculationParameters $parameters): self
    {
        return self::default();
    }

    /**
     * @param Coordinates|array{0: mixed, 1: mixed} $coordinates
     */
    public function calculate(Coordinates|array $coordinates, CivilDate $date, CalculationParameters $parameters): PrayerSchedule
    {
        self::normalizeCoordinates($coordinates);

        throw new \LogicException('not implemented: M1 prayer pipeline pending Appendix A goldens');
    }
}
