<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

enum SslStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Installing = 'installing';
    case Active = 'active';
    case Expired = 'expired';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Pending => 'Pending',
            self::Installing => 'Installing',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Failed => 'Failed',
            self::Unknown => 'Unknown',
        };
    }

    public function isSecure(): bool
    {
        return $this === self::Active;
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Expired, self::Failed, self::None], true);
    }

    public static function fromString(?string $status): self
    {
        if ($status === null) {
            return self::Unknown;
        }

        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'none', 'disabled', 'off', '' => self::None,
            'pending', 'requested', 'validating' => self::Pending,
            'installing', 'provisioning', 'creating', 'issuing' => self::Installing,
            'active', 'enabled', 'valid', 'issued', 'installed', 'on' => self::Active,
            'expired', 'revoked' => self::Expired,
            'failed', 'error', 'invalid' => self::Failed,
            default => self::tryFrom($normalized) ?? self::Unknown,
        };
    }
}
