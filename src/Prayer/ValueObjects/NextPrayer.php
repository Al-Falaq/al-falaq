<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\ValueObjects;

use AlFalaq\AlFalaq\Prayer\Enums\Prayer;

/**
 * Resolver answer: which prayer, at what instant, in how many seconds.
 */
final readonly class NextPrayer
{
    /**
     * Resolver answer triple.
     *
     * @param Prayer $prayer The upcoming prayer.
     * @param \DateTimeImmutable $at Its instant (UTC).
     * @param int $inSeconds Seconds from now until it.
     */
    public function __construct(
        public Prayer $prayer,
        public \DateTimeImmutable $at,
        public int $inSeconds,
    ) {}
}
