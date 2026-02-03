<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;
use Pstoute\LaravelHosting\Enums\DeploymentStatus;

final class Deployment implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly string $id,
        public readonly string $siteId,
        public readonly DeploymentStatus $status = DeploymentStatus::Unknown,
        public readonly ?string $commitHash = null,
        public readonly ?string $commitMessage = null,
        public readonly ?string $commitAuthor = null,
        public readonly ?string $branch = null,
        public readonly ?string $triggeredBy = null,
        public readonly ?string $output = null,
        public readonly ?DateTimeImmutable $startedAt = null,
        public readonly ?DateTimeImmutable $finishedAt = null,
        public readonly ?int $durationSeconds = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a Deployment instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $startedAt = self::parseDateTime($data['started_at'] ?? $data['created_at'] ?? null);
        $finishedAt = self::parseDateTime($data['finished_at'] ?? $data['ended_at'] ?? $data['completed_at'] ?? null);

        $duration = null;
        if (isset($data['duration_seconds'])) {
            $duration = (int) $data['duration_seconds'];
        } elseif (isset($data['duration'])) {
            $duration = (int) $data['duration'];
        } elseif ($startedAt && $finishedAt) {
            $duration = $finishedAt->getTimestamp() - $startedAt->getTimestamp();
        }

        return new self(
            id: (string) ($data['id'] ?? $data['deployment_id'] ?? ''),
            siteId: (string) ($data['site_id'] ?? ''),
            status: DeploymentStatus::fromString($data['status'] ?? null),
            commitHash: $data['commit_hash'] ?? $data['commit'] ?? $data['sha'] ?? null,
            commitMessage: $data['commit_message'] ?? $data['message'] ?? null,
            commitAuthor: $data['commit_author'] ?? $data['author'] ?? null,
            branch: $data['branch'] ?? $data['git_branch'] ?? null,
            triggeredBy: $data['triggered_by'] ?? $data['user'] ?? $data['initiator'] ?? null,
            output: $data['output'] ?? $data['log'] ?? null,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            durationSeconds: $duration,
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
     * Check if the deployment is complete
     */
    public function isComplete(): bool
    {
        return $this->status->isComplete();
    }

    /**
     * Check if the deployment is running
     */
    public function isRunning(): bool
    {
        return $this->status->isRunning();
    }

    /**
     * Check if the deployment was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    /**
     * Get the duration as a human-readable string
     */
    public function humanReadableDuration(): ?string
    {
        if ($this->durationSeconds === null) {
            return null;
        }

        if ($this->durationSeconds < 60) {
            return $this->durationSeconds . 's';
        }

        $minutes = floor($this->durationSeconds / 60);
        $seconds = $this->durationSeconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$seconds}s";
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        return "{$hours}h {$minutes}m {$seconds}s";
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
