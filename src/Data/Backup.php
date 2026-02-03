<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;
use Pstoute\LaravelHosting\Enums\BackupStatus;
use Pstoute\LaravelHosting\Enums\BackupType;

final class Backup implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly string $id,
        public readonly string $siteId,
        public readonly BackupStatus $status = BackupStatus::Unknown,
        public readonly BackupType $type = BackupType::Full,
        public readonly ?int $sizeBytes = null,
        public readonly ?string $description = null,
        public readonly ?string $storagePath = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $completedAt = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a Backup instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['backup_id'] ?? ''),
            siteId: (string) ($data['site_id'] ?? ''),
            status: BackupStatus::fromString($data['status'] ?? null),
            type: BackupType::fromString($data['type'] ?? $data['backup_type'] ?? null),
            sizeBytes: isset($data['size_bytes']) ? (int) $data['size_bytes'] : (isset($data['size']) ? (int) $data['size'] : null),
            description: $data['description'] ?? $data['note'] ?? $data['label'] ?? null,
            storagePath: $data['storage_path'] ?? $data['path'] ?? $data['url'] ?? null,
            createdAt: self::parseDateTime($data['created_at'] ?? null),
            completedAt: self::parseDateTime($data['completed_at'] ?? $data['finished_at'] ?? null),
            expiresAt: self::parseDateTime($data['expires_at'] ?? $data['expiry'] ?? null),
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
     * Check if the backup is complete
     */
    public function isComplete(): bool
    {
        return $this->status->isComplete();
    }

    /**
     * Check if the backup is still running
     */
    public function isRunning(): bool
    {
        return $this->status->isRunning();
    }

    /**
     * Check if the backup has expired
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
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
