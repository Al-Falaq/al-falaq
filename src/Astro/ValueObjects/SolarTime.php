<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Astro\ValueObjects;

/** @internal */
final readonly class SolarTime
{
    public function __construct(
        public \DateTimeImmutable $transit,
        public \DateTimeImmutable $sunrise,
        public \DateTimeImmutable $sunset,
    ) {}
}
