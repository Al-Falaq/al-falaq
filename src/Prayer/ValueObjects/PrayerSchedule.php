<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\Prayer\ValueObjects;

use AlFalaq\AlFalak\Prayer\Enums\Prayer;
use AlFalaq\AlFalak\SharedKernel\Exceptions\ValidationException;

final readonly class PrayerSchedule
{
    public function __construct(
        public \DateTimeImmutable $imsak,
        public \DateTimeImmutable $fajr,
        public \DateTimeImmutable $sunrise,
        public \DateTimeImmutable $syuruk,
        public \DateTimeImmutable $ishraq,
        public \DateTimeImmutable $dhuha,
        public \DateTimeImmutable $dhuhr,
        public \DateTimeImmutable $asr,
        public \DateTimeImmutable $maghrib,
        public \DateTimeImmutable $isha,
    ) {
        foreach (['imsak' => $imsak, 'fajr' => $fajr, 'sunrise' => $sunrise, 'dhuhr' => $dhuhr, 'asr' => $asr, 'maghrib' => $maghrib, 'isha' => $isha] as $name => $dt) {
            if ($dt->getTimezone()->getName() !== 'UTC') {
                throw new ValidationException(sprintf('PrayerSchedule::%s must be UTC.', $name));
            }
        }
    }

    public function timeFor(Prayer $prayer): \DateTimeImmutable
    {
        return match ($prayer) {
            Prayer::IMSAK => $this->imsak,
            Prayer::FAJR => $this->fajr,
            Prayer::SUNRISE => $this->sunrise,
            Prayer::SYURUK => $this->syuruk,
            Prayer::ISHRAQ => $this->ishraq,
            Prayer::DHUHA => $this->dhuha,
            Prayer::DHUHR => $this->dhuhr,
            Prayer::ASR => $this->asr,
            Prayer::MAGHRIB => $this->maghrib,
            Prayer::ISHA => $this->isha,
        };
    }

    /** @return array<string, \DateTimeImmutable> */
    public function all(): array
    {
        return [
            'imsak' => $this->imsak, 'fajr' => $this->fajr, 'sunrise' => $this->sunrise,
            'syuruk' => $this->syuruk, 'ishraq' => $this->ishraq, 'dhuha' => $this->dhuha,
            'dhuhr' => $this->dhuhr, 'asr' => $this->asr, 'maghrib' => $this->maghrib, 'isha' => $this->isha,
        ];
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $out = [];

        foreach ($this->all() as $key => $dt) {
            $out[$key] = $dt->format('Y-m-d\TH:i:sP');
        }

        return $out;
    }

    public function forZone(\DateTimeZone $zone): DisplaySchedule
    {
        return new DisplaySchedule($this, $zone);
    }

    public function nightLength(): \DateInterval
    {
        return $this->maghrib->diff($this->isha);
    }
}
