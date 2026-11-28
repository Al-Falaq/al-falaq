<?php

declare(strict_types=1);

namespace AlFalaq\AlFalaq\SharedKernel\ValueObjects;

use AlFalaq\AlFalaq\SharedKernel\Exceptions\ValidationException;

/**
 * Runtime library version reported in metadata, independent of schema version.
 *
 * CURRENT is the checked-in runtime version. Release preparation bumps it with
 * the changelog: the tagged version is exact (`0.3.0`) and development uses a
 * `-dev` suffix.
 */
final readonly class LibraryVersion
{
    /**
     * Checked-in runtime version for this release.
     */
    public const CURRENT = '0.3.0';

    /**
     * Bind a validated version string.
     *
     * @param string $value Version matching the documented grammar.
     */
    private function __construct(public string $value) {}

    /**
     * The checked-in runtime version.
     *
     * @return self Current version.
     */
    public static function current(): self
    {
        return new self(self::CURRENT);
    }

    /**
     * Restore a version string (tests and historical metadata reconstruction).
     *
     * @param string $value Version matching `N.N.N` with an optional prerelease.
     * @return self Validated version.
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/', $value) !== 1) {
            throw new ValidationException(sprintf('Invalid library version "%s".', $value));
        }

        return new self($value);
    }

    /**
     * Version string as published in metadata.
     *
     * @return string The version.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
