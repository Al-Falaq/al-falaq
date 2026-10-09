<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

final readonly class CivilDate
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    public static function fromYmd(int $year, int $month, int $day): self
    {
        if ($year < 1900 || $year > 2100) {
            throw new ValidationException(sprintf('Invalid year %d: supported range is 1900–2100.', $year));
        }

        if (! checkdate($month, $day, $year)) {
            throw new ValidationException(sprintf('Invalid civil date %04d-%02d-%02d.', $year, $month, $day));
        }

        return new self($year, $month, $day);
    }

    public static function fromDateTime(\DateTimeImmutable $dateTime): self
    {
        return self::fromYmd(
            (int) $dateTime->format('Y'),
            (int) $dateTime->format('n'),
            (int) $dateTime->format('j'),
        );
    }

    public function toUtcMidnight(): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            sprintf('%d-%d-%d', $this->year, $this->month, $this->day),
            new \DateTimeZone('UTC'),
        );

        if ($date === false) {
            throw new ValidationException('Unable to build UTC midnight for civil date.');
        }

        return $date;
    }

    public function plusDays(int $days): self
    {
        $moved = $this->toUtcMidnight()->modify(sprintf('%+d days', $days));

        if ($moved === false) {
            throw new ValidationException('Unable to add days to civil date.');
        }

        return self::fromDateTime($moved);
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month && $this->day === $other->day;
    }

    public function compareTo(self $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }

    public function isoformat(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }
}
