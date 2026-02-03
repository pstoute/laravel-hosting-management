<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;
use Pstoute\LaravelHosting\Enums\SslStatus;

final class SslCertificate implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly string $id,
        public readonly string $siteId,
        public readonly SslStatus $status = SslStatus::Unknown,
        public readonly string $provider = 'letsencrypt',
        public readonly bool $autoRenewal = true,
        public readonly ?DateTimeImmutable $issuedAt = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        /** @var array<string> */
        public readonly array $domains = [],
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create an SslCertificate instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['ssl_id'] ?? $data['certificate_id'] ?? ''),
            siteId: (string) ($data['site_id'] ?? ''),
            status: SslStatus::fromString($data['status'] ?? ($data['enabled'] ?? false ? 'active' : 'none')),
            provider: (string) ($data['provider'] ?? $data['type'] ?? $data['issuer'] ?? 'letsencrypt'),
            autoRenewal: (bool) ($data['auto_renewal'] ?? $data['auto_renew'] ?? true),
            issuedAt: self::parseDateTime($data['issued_at'] ?? $data['created_at'] ?? null),
            expiresAt: self::parseDateTime($data['expires_at'] ?? $data['expiry'] ?? $data['expiration'] ?? null),
            domains: (array) ($data['domains'] ?? ($data['domain'] ? [$data['domain']] : [])),
            metadata: $data['metadata'] ?? $data['meta'] ?? [],
        );
    }

    /**
     * Parse a datetime value
     */
    private static function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        if (is_int($value)) {
            return (new DateTimeImmutable())->setTimestamp($value);
        }

        return null;
    }

    /**
     * Check if the certificate is valid
     */
    public function isValid(): bool
    {
        return $this->status->isSecure() && !$this->isExpired();
    }

    /**
     * Check if the certificate is expired
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * Check if the certificate expires soon (within the given days)
     */
    public function expiresSoon(int $days = 30): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        $threshold = (new DateTimeImmutable())->modify("+{$days} days");

        return $this->expiresAt < $threshold;
    }

    /**
     * Get days until expiration
     */
    public function daysUntilExpiration(): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        $diff = $now->diff($this->expiresAt);

        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
