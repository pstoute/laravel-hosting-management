<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

enum SiteStatus: string
{
    case Installing = 'installing';
    case Active = 'active';
    case Suspended = 'suspended';
    case Maintenance = 'maintenance';
    case Failed = 'failed';
    case Deleting = 'deleting';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Installing => 'Installing',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Maintenance => 'Maintenance',
            self::Failed => 'Failed',
            self::Deleting => 'Deleting',
            self::Unknown => 'Unknown',
        };
    }

    public function isOperational(): bool
    {
        return $this === self::Active;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Installing, self::Deleting], true);
    }

    public static function fromString(?string $status): self
    {
        if ($status === null) {
            return self::Unknown;
        }

        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'installing', 'provisioning', 'building', 'creating', 'pending' => self::Installing,
            'active', 'running', 'ready', 'online', 'enabled' => self::Active,
            'suspended', 'disabled', 'paused', 'locked' => self::Suspended,
            'maintenance', 'updating' => self::Maintenance,
            'failed', 'error' => self::Failed,
            'deleting', 'removing' => self::Deleting,
            default => self::tryFrom($normalized) ?? self::Unknown,
        };
    }
}
