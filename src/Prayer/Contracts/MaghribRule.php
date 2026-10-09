<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

interface MaghribRule
{
    public function minutesAfterSunset(): ?int;

    public function angle(): ?float;
}
