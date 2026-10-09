<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

final readonly class SunnahTimes
{
    public function __construct(
        public \DateTimeImmutable $middleOfNight,
        public \DateTimeImmutable $firstThird,
        public \DateTimeImmutable $lastThird,
        public \DateTimeImmutable $fajr,
    ) {
        if ($fajr <= $lastThird) {
            throw new ValidationException('Degenerate Sunnah night: fajr must be after last third.');
        }
    }

    /** @return array{from: \DateTimeImmutable, to: \DateTimeImmutable} */
    public function tahajjudWindow(): array
    {
        return ['from' => $this->lastThird, 'to' => $this->fajr];
    }

    public function nightFraction(float $fraction): \DateTimeImmutable
    {
        if (! is_finite($fraction) || $fraction <= 0.0 || $fraction >= 1.0) {
            throw new ValidationException('nightFraction expects f in (0, 1) exclusive.');
        }

        throw new \LogicException('not implemented: SunnahCalculator pending M2');
    }

    public function forZone(\DateTimeZone $zone): self
    {
        return new self(
            $this->middleOfNight->setTimezone($zone),
            $this->firstThird->setTimezone($zone),
            $this->lastThird->setTimezone($zone),
            $this->fajr->setTimezone($zone),
        );
    }
}
