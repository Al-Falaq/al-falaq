<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Strategies;

use AlFalaq\AlFalak\Prayer\Contracts\TwilightModel;

final class SeasonAdjustedTwilight implements TwilightModel
{
    public function adjustedAngles(int $dayOfYear, float $latitude): array
    {
        throw new \LogicException('not implemented: M1 MSC twilight pending Appendix B transcription');
    }

    public function seasonAdjustedMorning(int $dayOfYear, float $latitude): float
    {
        throw new \LogicException('not implemented: M1 MSC twilight pending');
    }

    public function seasonAdjustedEvening(int $dayOfYear, float $latitude): float
    {
        throw new \LogicException('not implemented: M1 MSC twilight pending');
    }
}
