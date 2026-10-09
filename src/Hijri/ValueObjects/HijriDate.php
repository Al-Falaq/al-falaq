<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Hijri\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

final readonly class HijriDate
{
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
        if ($month < 1 || $month > 12) {
            throw new ValidationException(sprintf('Invalid Hijri month %d.', $month));
        }

        if ($day < 1 || $day > 30) {
            throw new ValidationException(sprintf('Invalid Hijri day %d: universal bound is 1..30.', $day));
        }
    }

    public function plusDays(int $days): self
    {
        throw new \LogicException('not implemented: M4 Hijri arithmetic pending calendar month lengths');
    }

    public function isoformat(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function compareTo(self $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }
}
