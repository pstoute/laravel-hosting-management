<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data;

use JsonSerializable;
use Pstoute\LaravelHosting\Data\Concerns\HasArrayAccess;

final class ConnectionResult implements JsonSerializable
{
    use HasArrayAccess;

    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?int $statusCode = null,
        public readonly ?int $latencyMs = null,
        /** @var array<string, mixed> */
        public readonly array $data = [],
    ) {}

    /**
     * Create a successful connection result
     *
     * @param array<string, mixed> $data
     */
    public static function success(string $message = 'Connection successful', array $data = [], ?int $latencyMs = null): self
    {
        return new self(
            success: true,
            message: $message,
            statusCode: 200,
            latencyMs: $latencyMs,
            data: $data,
        );
    }

    /**
     * Create a failed connection result
     *
     * @param array<string, mixed> $data
     */
    public static function failure(string $message, ?int $statusCode = null, array $data = []): self
    {
        return new self(
            success: false,
            message: $message,
            statusCode: $statusCode,
            latencyMs: null,
            data: $data,
        );
    }

    /**
     * Create from an array
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: (bool) ($data['success'] ?? false),
            message: (string) ($data['message'] ?? ''),
            statusCode: isset($data['status_code']) ? (int) $data['status_code'] : null,
            latencyMs: isset($data['latency_ms']) ? (int) $data['latency_ms'] : null,
            data: $data['data'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
