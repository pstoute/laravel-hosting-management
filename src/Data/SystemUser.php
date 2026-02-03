<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;

final class SystemUser implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $serverId,
        public readonly bool $isIsolated = false,
        public readonly bool $hasSshAccess = false,
        public readonly ?string $homeDirectory = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        /** @var array<string> */
        public readonly array $groups = [],
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a SystemUser instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['user_id'] ?? ''),
            username: (string) ($data['username'] ?? $data['name'] ?? $data['user'] ?? ''),
            serverId: (string) ($data['server_id'] ?? ''),
            isIsolated: (bool) ($data['is_isolated'] ?? $data['isolated'] ?? false),
            hasSshAccess: (bool) ($data['has_ssh_access'] ?? $data['ssh_access'] ?? $data['ssh'] ?? false),
            homeDirectory: $data['home_directory'] ?? $data['home'] ?? null,
            createdAt: self::parseDateTime($data['created_at'] ?? null),
            groups: (array) ($data['groups'] ?? []),
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
     * Check if the user belongs to a specific group
     */
    public function inGroup(string $group): bool
    {
        return in_array($group, $this->groups, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
