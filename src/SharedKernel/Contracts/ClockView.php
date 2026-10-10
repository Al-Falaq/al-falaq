<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\Contracts;

/**
 * Minimal clock view for edges that need "now".
 *
 * The domain never reads the clock; callers pass time explicitly. Host apps
 * using psr/clock adapt it to this single-method shape at the edge.
 */
interface ClockView
{
    /**
     * Current instant.
     *
     * @return \DateTimeImmutable now, with an explicit timezone.
     */
    public function now(): \DateTimeImmutable;
}
