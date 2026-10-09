<?php

declare(strict_types=1);

namespace AlFalaq\AlFalak\SharedKernel\ValueObjects;

use AlFalaq\AlFalak\SharedKernel\Traits\ValidatesFinite;
use AlFalaq\AlFalak\SharedKernel\Exceptions\InvalidCoordinatesException;

final readonly class Coordinates
{
    use ValidatesFinite;

    private function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

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
     * @param array<array-key, mixed> $pair
     */
    public static function fromArray(array $pair): self
    {
        if (count($pair) !== 2 || ! array_key_exists(0, $pair) || ! array_key_exists(1, $pair)) {
            throw InvalidCoordinatesException::malformedArray();
        }

        return self::fromDegrees($pair[0], $pair[1]);
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude];
    }

    public function __toString(): string
    {
        return sprintf('%.6f,%.6f', $this->latitude, $this->longitude);
    }
}
