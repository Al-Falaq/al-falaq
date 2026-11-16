<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Enums\SourceKind;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * One stable identity for an algorithm, data table, or caller-supplied input.
 *
 * Ids are lowercase and stable within schema major version 1. Built-in
 * strategies expose their id as a class constant; synthetic sources (method
 * presets, caller inputs) are built by the service.
 */
final readonly class CalculationSource
{
    /**
     * Bind a validated source (factories are the only construction path).
     *
     * @param string $id Stable lowercase identifier.
     * @param SourceKind $kind Dependency kind.
     * @param string|null $version Model or table version, when known.
     * @param string|null $digest Lowercase SHA-256 for table data, when known.
     * @param string|null $range Calibrated or covered range, when known.
     */
    private function __construct(
        public string $id,
        public SourceKind $kind,
        public ?string $version,
        public ?string $digest,
        public ?string $range,
    ) {}

    /**
     * Algorithm or model source, optionally versioned or range-limited.
     *
     * @param string $id Stable lowercase identifier.
     * @param string|null $version Model version.
     * @param string|null $range Calibrated model range.
     * @return self Validated algorithm source.
     */
    public static function algorithm(string $id, ?string $version = null, ?string $range = null): self
    {
        self::assertId($id);

        return new self(
            $id,
            SourceKind::ALGORITHM,
            self::nonEmptyOrNull($version, 'version'),
            null,
            self::nonEmptyOrNull($range, 'range'),
        );
    }

    /**
     * Generated data table with a required digest and coverage range.
     *
     * @param string $id Stable lowercase identifier.
     * @param string $digest Lowercase SHA-256 of the table values.
     * @param string $range Covered date range.
     * @param string|null $version Table version.
     * @return self Validated generated-data source.
     */
    public static function generatedData(string $id, string $digest, string $range, ?string $version = null): self
    {
        self::assertId($id);

        return new self(
            $id,
            SourceKind::GENERATED_DATA,
            self::nonEmptyOrNull($version, 'version'),
            self::assertDigest($digest),
            self::assertNonEmpty($range, 'range'),
        );
    }

    /**
     * Official data table with a required digest and coverage range.
     *
     * @param string $id Stable lowercase identifier.
     * @param string $digest Lowercase SHA-256 of the table values.
     * @param string $range Covered date range.
     * @param string|null $version Table version.
     * @return self Validated official-data source.
     */
    public static function officialData(string $id, string $digest, string $range, ?string $version = null): self
    {
        self::assertId($id);

        return new self(
            $id,
            SourceKind::OFFICIAL_DATA,
            self::nonEmptyOrNull($version, 'version'),
            self::assertDigest($digest),
            self::assertNonEmpty($range, 'range'),
        );
    }

    /**
     * Caller-supplied input; the caller owns the value's provenance.
     *
     * @param string $id Stable lowercase identifier.
     * @return self Validated caller-input source.
     */
    public static function callerInput(string $id): self
    {
        self::assertId($id);

        return new self($id, SourceKind::CALLER_INPUT, null, null, null);
    }

    /**
     * Wire shape; all five keys are always present.
     *
     * @return array{id: string, kind: string, version: string|null, digest: string|null, range: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'version' => $this->version,
            'digest' => $this->digest,
            'range' => $this->range,
        ];
    }

    /**
     * Validate the stable-id grammar.
     *
     * @param string $id Candidate identifier.
     * @return void Throws when the id is not lowercase or contains forbidden characters.
     */
    private static function assertId(string $id): void
    {
        if (preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $id) !== 1) {
            throw new ValidationException(sprintf('Invalid calculation source id "%s".', $id));
        }
    }

    /**
     * Keep null, or validate that a present value is non-empty.
     *
     * @param string|null $value Candidate value.
     * @param string $name Field name for the error message.
     * @return string|null The original value or null.
     */
    private static function nonEmptyOrNull(?string $value, string $name): ?string
    {
        if ($value !== null && trim($value) === '') {
            throw new ValidationException(sprintf('Calculation source %s must not be empty.', $name));
        }

        return $value;
    }

    /**
     * Require a non-empty value.
     *
     * @param string $value Candidate value.
     * @param string $name Field name for the error message.
     * @return string The validated value.
     */
    private static function assertNonEmpty(string $value, string $name): string
    {
        if (trim($value) === '') {
            throw new ValidationException(sprintf('Calculation source %s must not be empty.', $name));
        }

        return $value;
    }

    /**
     * Validate a lowercase SHA-256 digest.
     *
     * @param string $digest Candidate digest.
     * @return string The validated digest.
     */
    private static function assertDigest(string $digest): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $digest) !== 1) {
            throw new ValidationException(sprintf('Invalid calculation source digest "%s".', $digest));
        }

        return $digest;
    }
}
