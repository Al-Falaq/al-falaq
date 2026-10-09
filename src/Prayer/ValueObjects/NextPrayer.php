<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

use AlFalaq\AlFalak\Prayer\Enums\Prayer;

final readonly class NextPrayer
{
    public function __construct(
        public Prayer $prayer,
        public \DateTimeImmutable $at,
        public int $inSeconds,
    ) {}
}
