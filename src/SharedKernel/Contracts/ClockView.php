<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\Contracts;

interface ClockView
{
    public function now(): \DateTimeImmutable;
}
