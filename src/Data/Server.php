<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\ServerProvider;
use Pstoute\LaravelHosting\Enums\ServerStatus;

final class Server implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ServerStatus $status = ServerStatus::Unknown,
        public readonly ?string $ipAddress = null,
        public readonly ?string $privateIpAddress = null,
        public readonly ?PhpVersion $phpVersion = null,
        public readonly ?ServerProvider $serverProvider = null,
        public readonly ?string $region = null,
        public readonly ?string $size = null,
        public readonly ?string $ubuntuVersion = null,
        public readonly ?string $databaseType = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $provisionedAt = null,
        public readonly ?ServerMetrics $metrics = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a Server instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['server_id'] ?? ''),
            name: (string) ($data['name'] ?? $data['server_name'] ?? ''),
            status: ServerStatus::fromString($data['status'] ?? null),
            ipAddress: $data['ip_address'] ?? $data['ip'] ?? $data['public_ip'] ?? null,
            privateIpAddress: $data['private_ip_address'] ?? $data['private_ip'] ?? null,
            phpVersion: isset($data['php_version']) ? PhpVersion::fromString($data['php_version']) : null,
            serverProvider: ServerProvider::fromString($data['server_provider'] ?? $data['provider'] ?? $data['cloud_provider'] ?? null),
            region: $data['region'] ?? $data['datacenter'] ?? null,
            size: $data['size'] ?? $data['plan'] ?? $data['type'] ?? null,
            ubuntuVersion: $data['ubuntu_version'] ?? $data['os_version'] ?? null,
            databaseType: $data['database_type'] ?? $data['db_type'] ?? null,
            createdAt: self::parseDateTime($data['created_at'] ?? null),
            provisionedAt: self::parseDateTime($data['provisioned_at'] ?? $data['ready_at'] ?? null),
            metrics: isset($data['metrics']) ? ServerMetrics::fromArray($data['metrics']) : null,
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

    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
