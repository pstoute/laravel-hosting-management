<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;

final class Site implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly string $id,
        public readonly string $serverId,
        public readonly string $domain,
        public readonly SiteStatus $status = SiteStatus::Unknown,
        public readonly ?PhpVersion $phpVersion = null,
        public readonly bool $sslEnabled = false,
        public readonly SslStatus $sslStatus = SslStatus::None,
        public readonly ?string $sslExpiresAt = null,
        public readonly ?string $documentRoot = null,
        public readonly ?string $systemUser = null,
        public readonly ?string $projectType = null,
        public readonly bool $isWordPress = false,
        public readonly bool $isStaging = false,
        public readonly ?string $productionSiteId = null,
        public readonly ?string $repository = null,
        public readonly ?string $repositoryBranch = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $lastDeployedAt = null,
        /** @var array<string> */
        public readonly array $aliases = [],
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a Site instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['site_id'] ?? ''),
            serverId: (string) ($data['server_id'] ?? $data['serverId'] ?? ''),
            domain: (string) ($data['domain'] ?? $data['name'] ?? $data['hostname'] ?? ''),
            status: SiteStatus::fromString($data['status'] ?? null),
            phpVersion: isset($data['php_version']) ? PhpVersion::fromString($data['php_version']) : null,
            sslEnabled: (bool) ($data['ssl_enabled'] ?? $data['ssl'] ?? $data['https'] ?? false),
            sslStatus: SslStatus::fromString($data['ssl_status'] ?? ($data['ssl_enabled'] ?? false ? 'active' : 'none')),
            sslExpiresAt: $data['ssl_expires_at'] ?? $data['ssl_expiry'] ?? null,
            documentRoot: $data['document_root'] ?? $data['web_root'] ?? $data['root'] ?? null,
            systemUser: $data['system_user'] ?? $data['site_user'] ?? $data['user'] ?? null,
            projectType: $data['project_type'] ?? $data['type'] ?? null,
            isWordPress: (bool) ($data['is_wordpress'] ?? $data['wordpress'] ?? $data['wp'] ?? false),
            isStaging: (bool) ($data['is_staging'] ?? $data['staging'] ?? false),
            productionSiteId: $data['production_site_id'] ?? $data['production_id'] ?? null,
            repository: $data['repository'] ?? $data['git_repo'] ?? $data['repo'] ?? null,
            repositoryBranch: $data['repository_branch'] ?? $data['branch'] ?? $data['git_branch'] ?? null,
            createdAt: self::parseDateTime($data['created_at'] ?? null),
            lastDeployedAt: self::parseDateTime($data['last_deployed_at'] ?? $data['deployed_at'] ?? null),
            aliases: (array) ($data['aliases'] ?? $data['site_aliases'] ?? []),
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

    public function hasValidSsl(): bool
    {
        return $this->sslEnabled && $this->sslStatus->isSecure();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
