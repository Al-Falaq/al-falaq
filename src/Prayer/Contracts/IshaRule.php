<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\Contracts;

interface IshaRule
{
    public function minutesAfterMaghrib(): ?int;

    public function angle(): ?float;
}
