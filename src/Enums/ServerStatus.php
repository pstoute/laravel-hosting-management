<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

enum ServerStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Inactive = 'inactive';
    case Rebooting = 'rebooting';
    case Failed = 'failed';
    case Deleting = 'deleting';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Provisioning',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Rebooting => 'Rebooting',
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
        return in_array($this, [self::Provisioning, self::Rebooting, self::Deleting], true);
    }

    public static function fromString(?string $status): self
    {
        if ($status === null) {
            return self::Unknown;
        }

        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'provisioning', 'installing', 'building', 'creating' => self::Provisioning,
            'active', 'running', 'ready', 'online', 'connected' => self::Active,
            'inactive', 'stopped', 'offline', 'disconnected' => self::Inactive,
            'rebooting', 'restarting' => self::Rebooting,
            'failed', 'error' => self::Failed,
            'deleting', 'removing', 'terminating' => self::Deleting,
            default => self::tryFrom($normalized) ?? self::Unknown,
        };
    }
}
