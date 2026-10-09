<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Traits\ValidatesFinite;
use AlFalaq\AlFalak\SharedKernel\Exceptions\InvalidCoordinatesException;

/**
 * Validated geographic coordinates (lat −90…90, lon −180…180, WGS84 degrees).
 */
final readonly class Coordinates
{
    use ValidatesFinite;

    private function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * Build from degrees; ints normalize to float, bools/non-finite reject.
     *
     * @param mixed $latitude −90…90.
     * @param mixed $longitude −180…180.
     * @return self Validated coordinates.
     */
    public static function fromDegrees(mixed $latitude, mixed $longitude): self
    {
        $lat = self::assertFinite($latitude, 'latitude');
        $lon = self::assertFinite($longitude, 'longitude');

        if ($lat < -90.0 || $lat > 90.0) {
            throw InvalidCoordinatesException::outOfRange('lat', $latitude);
        }

        if ($lon < -180.0 || $lon > 180.0) {
            throw InvalidCoordinatesException::outOfRange('lon', $longitude);
        }

        return new self($lat, $lon);
    }

    /**
     * Build from a positional pair; assoc arrays are rejected (swap guard).
     *
     * @param array<array-key, mixed> $pair [lat, lon] with integer keys 0 and 1 only.
     * @return self Validated coordinates.
     */
    public static function fromArray(array $pair): self
    {
        if (count($pair) !== 2 || ! array_key_exists(0, $pair) || ! array_key_exists(1, $pair)) {
            throw InvalidCoordinatesException::malformedArray();
        }

        return self::fromDegrees($pair[0], $pair[1]);
    }

    /**
     * Exact float equality (no tolerance — normalize inputs first).
     *
     * @param self $other Coordinates to compare against.
     * @return bool True when both axes match exactly.
     */
    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }

    /**
     * Named-shape export for JSON APIs and timetables.
     *
     * @return array{latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude];
    }

    /**
     * Compact "lat,lon" with 6 decimals for logs and golden keys.
     *
     * @return string e.g. '3.139000,101.686900'.
     */
    public function __toString(): string
    {
        return sprintf('%.6f,%.6f', $this->latitude, $this->longitude);
    }
}
