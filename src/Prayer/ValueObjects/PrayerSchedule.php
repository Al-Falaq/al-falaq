<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\Prayer\ValueObjects;

use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * One day of prayer instants, stored UTC-only (display via forZone()).
 *
 * The ctor rejects non-UTC inputs; user adjustments may break ordering —
 * the calculator never silently reorders (see Technical-PRD.md §5.2).
 */
final readonly class PrayerSchedule
{
    /**
     * Bind all ten UTC instants (non-UTC inputs throw).
     *
     * @param \DateTimeImmutable $imsak Fajr minus imsak offset (UTC).
     * @param \DateTimeImmutable $fajr Dawn prayer (UTC).
     * @param \DateTimeImmutable $sunrise Sunrise (UTC).
     * @param \DateTimeImmutable $syuruk Sunrise alias, MY/SG naming (UTC).
     * @param \DateTimeImmutable $ishraq Sunrise plus offset (UTC).
     * @param \DateTimeImmutable $dhuha Forenoon (UTC).
     * @param \DateTimeImmutable $dhuhr Solar transit plus offset (UTC).
     * @param \DateTimeImmutable $asr Afternoon (UTC).
     * @param \DateTimeImmutable $maghrib Sunset (UTC).
     * @param \DateTimeImmutable $isha Night prayer (UTC).
     */
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
        foreach (['imsak' => $imsak, 'fajr' => $fajr, 'sunrise' => $sunrise, 'syuruk' => $syuruk, 'ishraq' => $ishraq, 'dhuha' => $dhuha, 'dhuhr' => $dhuhr, 'asr' => $asr, 'maghrib' => $maghrib, 'isha' => $isha] as $name => $dt) {
            if ($dt->getTimezone()->getName() !== 'UTC') {
                throw new ValidationException(sprintf('PrayerSchedule::%s must be UTC.', $name));
            }
        }
    }

    /**
     * One prayer instant in UTC.
     *
     * @param Prayer $prayer Which marker.
     * @return \DateTimeImmutable Instant in UTC.
     */
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

    /**
     * All ten instants in UTC, in schedule order (Appendix C schema).
     *
     * @return array<string, \DateTimeImmutable> Keyed by Prayer value.
     */
    public function all(): array
    {
        return [
            'imsak' => $this->imsak, 'fajr' => $this->fajr, 'sunrise' => $this->sunrise,
            'syuruk' => $this->syuruk, 'ishraq' => $this->ishraq, 'dhuha' => $this->dhuha,
            'dhuhr' => $this->dhuhr, 'asr' => $this->asr, 'maghrib' => $this->maghrib, 'isha' => $this->isha,
        ];
    }

    /**
     * ISO8601 strings in UTC (JSON-ready timetable row, Appendix C schema).
     *
     * @return array<string, string> Keyed by Prayer value.
     */
    public function toArray(): array
    {
        $out = [];

        foreach ($this->all() as $key => $dt) {
            $out[$key] = $dt->format('Y-m-d\TH:i:sP');
        }

        return $out;
    }

    /**
     * Convert to a display-zone view (leaf conversion, math never rerun).
     *
     * @param \DateTimeZone $zone Display zone.
     * @return DisplaySchedule Zone-bound view of this schedule.
     */
    public function forZone(\DateTimeZone $zone): DisplaySchedule
    {
        return new DisplaySchedule($this, $zone);
    }

    /**
     * Maghrib→Isha span (a day-length measure, not the Sunnah night).
     *
     * @return \DateInterval Positive interval between the two instants.
     */
    public function nightLength(): \DateInterval
    {
        return $this->maghrib->diff($this->isha);
    }
}
