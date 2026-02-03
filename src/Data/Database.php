<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;

final class Database implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $serverId = null,
        public readonly ?string $siteId = null,
        public readonly ?string $host = null,
        public readonly int $port = 3306,
        public readonly string $type = 'mysql',
        public readonly ?int $sizeBytes = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a Database instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['database_id'] ?? ''),
            name: (string) ($data['name'] ?? $data['database_name'] ?? ''),
            serverId: $data['server_id'] ?? null,
            siteId: $data['site_id'] ?? null,
            host: $data['host'] ?? $data['hostname'] ?? 'localhost',
            port: (int) ($data['port'] ?? 3306),
            type: (string) ($data['type'] ?? $data['engine'] ?? 'mysql'),
            sizeBytes: isset($data['size_bytes']) ? (int) $data['size_bytes'] : (isset($data['size']) ? (int) $data['size'] : null),
            createdAt: self::parseDateTime($data['created_at'] ?? null),
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

        return null;
    }

    /**
     * Get human-readable size
     */
    public function humanReadableSize(): ?string
    {
        if ($this->sizeBytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->sizeBytes;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
