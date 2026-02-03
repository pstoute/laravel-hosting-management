<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

enum PhpVersion: string
{
    case PHP_74 = '7.4';
    case PHP_80 = '8.0';
    case PHP_81 = '8.1';
    case PHP_82 = '8.2';
    case PHP_83 = '8.3';
    case PHP_84 = '8.4';

    public function label(): string
    {
        return 'PHP ' . $this->value;
    }

    public function major(): int
    {
        return (int) explode('.', $this->value)[0];
    }

    public function minor(): int
    {
        return (int) explode('.', $this->value)[1];
    }

    public function isSupported(): bool
    {
        // PHP 7.4 reached EOL in Nov 2022, 8.0 in Nov 2023, 8.1 in Dec 2025
        return in_array($this, [self::PHP_82, self::PHP_83, self::PHP_84], true);
    }

    public function isLegacy(): bool
    {
        return in_array($this, [self::PHP_74, self::PHP_80], true);
    }

    public static function latest(): self
    {
        return self::PHP_84;
    }

    public static function recommended(): self
    {
        return self::PHP_83;
    }

    public static function fromString(?string $version): ?self
    {
        if ($version === null) {
            return null;
        }

        // Normalize the version string
        $normalized = preg_replace('/[^0-9.]/', '', $version);

        if ($normalized === null || $normalized === '') {
            return null;
        }

        // Extract major.minor
        $parts = explode('.', $normalized);
        if (count($parts) >= 2) {
            $majorMinor = $parts[0] . '.' . $parts[1];
            return self::tryFrom($majorMinor);
        }

        return self::tryFrom($normalized);
    }

    /**
     * Get all versions as an array of strings
     *
     * @return array<string>
     */
    public static function allVersions(): array
    {
        return array_map(fn (self $version) => $version->value, self::cases());
    }

    /**
     * Get only supported versions
     *
     * @return array<self>
     */
    public static function supported(): array
    {
        return array_filter(self::cases(), fn (self $version) => $version->isSupported());
    }
}
