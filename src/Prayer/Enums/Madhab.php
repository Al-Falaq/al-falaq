<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\Enums;

/**
 * Asr juristic school (drives the Asr shadow factor: 1.0 Shafi, 2.0 Hanafi).
 */
enum Madhab: string
{
    case SHAFI = 'shafi';
    case HANAFI = 'hanafi';

    /**
     * Shadow-length factor past solar noon for this school.
     *
     * @return float 1.0 (Shafi'i) or 2.0 (Hanafi).
     */
    public function shadowLength(): float
    {
        return $this === self::HANAFI ? 2.0 : 1.0;
    }
}
