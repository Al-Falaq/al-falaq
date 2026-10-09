<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Qibla\Services;

use AlFalaq\AlFalak\Qibla\Contracts\GeodesyCalculator;
use AlFalaq\AlFalak\Qibla\Strategies\SphericalCalculator;
use AlFalaq\AlFalak\Qibla\Strategies\Wgs84KarneyCalculator;
use AlFalaq\AlFalak\Qibla\ValueObjects\QiblaResult;
use AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates;

final class QiblaCalculator
{
    /** M0: geodesy accepted for API stability; wired into inverse() in M2. */
    public function __construct(private readonly ?GeodesyCalculator $geodesy = null) {}

    public static function default(): self
    {
        return new self(new SphericalCalculator());
    }

    public static function wgs84(): self
    {
        return new self(new Wgs84KarneyCalculator());
    }

    public function geodesy(): ?GeodesyCalculator
    {
        return $this->geodesy;
    }

    /**
     * @param Coordinates|array{0: mixed, 1: mixed} $coordinates
     */
    public function calculate(Coordinates|array $coordinates): QiblaResult
    {
        $coordinates instanceof Coordinates ? $coordinates : Coordinates::fromArray($coordinates);

        throw new \LogicException('not implemented: M2 qibla pending');
    }
}
