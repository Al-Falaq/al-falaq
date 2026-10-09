<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Enums;

enum Madhab: string
{
    case SHAFI = 'shafi';
    case HANAFI = 'hanafi';

    public function shadowLength(): float
    {
        return $this === self::HANAFI ? 2.0 : 1.0;
    }
}
