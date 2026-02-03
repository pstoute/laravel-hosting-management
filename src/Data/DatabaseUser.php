<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;

final class DatabaseUser implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly ?string $serverId = null,
        public readonly ?string $host = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        /** @var array<string> */
        public readonly array $databases = [],
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a DatabaseUser instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['user_id'] ?? ''),
            username: (string) ($data['username'] ?? $data['name'] ?? $data['user'] ?? ''),
            serverId: $data['server_id'] ?? null,
            host: $data['host'] ?? $data['hostname'] ?? '%',
            createdAt: self::parseDateTime($data['created_at'] ?? null),
            databases: (array) ($data['databases'] ?? []),
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
     * Check if the user has access to a specific database
     */
    public function hasAccessTo(string $databaseName): bool
    {
        return in_array($databaseName, $this->databases, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
