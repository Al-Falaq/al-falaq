<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Hijri\Enums;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

/**
 * MABIMS member states, used as pure table keys by MabimsCalendar.
 *
 * `referenceCoordinates()` records the national proxy point that the
 * Neo-MABIMS rule was evaluated at when the shipped tables were generated
 * (pin `MabimsCalendar.py` 4-dp values); it is documentation, not a runtime
 * input — conversion never re-evaluates the rule.
 */
enum MabimsCountry: string
{
    case MY = 'MY';
    case ID = 'ID';
    case SG = 'SG';
    case BN = 'BN';

    /**
     * National proxy reference point this country's table was generated from.
     *
     * @return Coordinates Proxy coordinates (pin's 4-dp values).
     */
    public function referenceCoordinates(): Coordinates
    {
        return match ($this) {
            self::MY => Coordinates::fromDegrees(3.1390, 101.6869),
            self::ID => Coordinates::fromDegrees(-6.2088, 106.8456),
            self::SG => Coordinates::fromDegrees(1.3521, 103.8198),
            self::BN => Coordinates::fromDegrees(4.8903, 114.9420),
        };
    }

    /**
     * Resolve a case-insensitive country code.
     *
     * @param string $code Two-letter code in any case ('my', 'Id', 'SG').
     * @return self Matching case.
     */
    public static function fromString(string $code): self
    {
        return self::tryFrom(strtoupper($code))
            ?? throw new ValidationException(sprintf('Unknown MABIMS country code %s.', $code));
    }
}
