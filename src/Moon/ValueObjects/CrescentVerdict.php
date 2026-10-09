<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Moon\ValueObjects;

final readonly class CrescentVerdict
{
    public function __construct(
        public string $criterion,
        public bool $visible,
        public float $score,
        public string $zone,
    ) {}
}
