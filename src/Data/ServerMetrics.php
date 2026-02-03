<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use DateTimeImmutable;
use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;

final class ServerMetrics implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly ?float $cpuUsage = null,
        public readonly ?float $memoryUsage = null,
        public readonly ?float $diskUsage = null,
        public readonly ?int $memoryTotalMb = null,
        public readonly ?int $memoryUsedMb = null,
        public readonly ?int $diskTotalGb = null,
        public readonly ?int $diskUsedGb = null,
        public readonly ?int $uptimeSeconds = null,
        public readonly ?float $loadAverage1m = null,
        public readonly ?float $loadAverage5m = null,
        public readonly ?float $loadAverage15m = null,
        public readonly ?int $networkInBytes = null,
        public readonly ?int $networkOutBytes = null,
        public readonly ?DateTimeImmutable $collectedAt = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a ServerMetrics instance from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cpuUsage: isset($data['cpu_usage']) ? (float) $data['cpu_usage'] : (isset($data['cpu']) ? (float) $data['cpu'] : null),
            memoryUsage: isset($data['memory_usage']) ? (float) $data['memory_usage'] : (isset($data['memory']) ? (float) $data['memory'] : null),
            diskUsage: isset($data['disk_usage']) ? (float) $data['disk_usage'] : (isset($data['disk']) ? (float) $data['disk'] : null),
            memoryTotalMb: isset($data['memory_total_mb']) ? (int) $data['memory_total_mb'] : (isset($data['memory_total']) ? (int) $data['memory_total'] : null),
            memoryUsedMb: isset($data['memory_used_mb']) ? (int) $data['memory_used_mb'] : (isset($data['memory_used']) ? (int) $data['memory_used'] : null),
            diskTotalGb: isset($data['disk_total_gb']) ? (int) $data['disk_total_gb'] : (isset($data['disk_total']) ? (int) $data['disk_total'] : null),
            diskUsedGb: isset($data['disk_used_gb']) ? (int) $data['disk_used_gb'] : (isset($data['disk_used']) ? (int) $data['disk_used'] : null),
            uptimeSeconds: isset($data['uptime_seconds']) ? (int) $data['uptime_seconds'] : (isset($data['uptime']) ? (int) $data['uptime'] : null),
            loadAverage1m: isset($data['load_average_1m']) ? (float) $data['load_average_1m'] : (isset($data['load_1']) ? (float) $data['load_1'] : null),
            loadAverage5m: isset($data['load_average_5m']) ? (float) $data['load_average_5m'] : (isset($data['load_5']) ? (float) $data['load_5'] : null),
            loadAverage15m: isset($data['load_average_15m']) ? (float) $data['load_average_15m'] : (isset($data['load_15']) ? (float) $data['load_15'] : null),
            networkInBytes: isset($data['network_in_bytes']) ? (int) $data['network_in_bytes'] : (isset($data['network_in']) ? (int) $data['network_in'] : null),
            networkOutBytes: isset($data['network_out_bytes']) ? (int) $data['network_out_bytes'] : (isset($data['network_out']) ? (int) $data['network_out'] : null),
            collectedAt: self::parseDateTime($data['collected_at'] ?? $data['timestamp'] ?? null),
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
     * Get uptime as a human-readable string
     */
    public function humanReadableUptime(): ?string
    {
        if ($this->uptimeSeconds === null) {
            return null;
        }

        $days = floor($this->uptimeSeconds / 86400);
        $hours = floor(($this->uptimeSeconds % 86400) / 3600);
        $minutes = floor(($this->uptimeSeconds % 3600) / 60);

        $parts = [];

        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = "{$hours}h";
        }
        $parts[] = "{$minutes}m";

        return implode(' ', $parts);
    }

    /**
     * Check if CPU usage is critical (above threshold)
     */
    public function isCpuCritical(float $threshold = 90.0): bool
    {
        return $this->cpuUsage !== null && $this->cpuUsage >= $threshold;
    }

    /**
     * Check if memory usage is critical (above threshold)
     */
    public function isMemoryCritical(float $threshold = 90.0): bool
    {
        return $this->memoryUsage !== null && $this->memoryUsage >= $threshold;
    }

    /**
     * Check if disk usage is critical (above threshold)
     */
    public function isDiskCritical(float $threshold = 90.0): bool
    {
        return $this->diskUsage !== null && $this->diskUsage >= $threshold;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
