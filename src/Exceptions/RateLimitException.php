<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Exceptions;

use Throwable;

class RateLimitException extends HostingException
{
    protected ?int $retryAfter = null;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'Rate limit exceeded',
        int $code = 429,
        ?Throwable $previous = null,
        array $context = [],
        ?int $retryAfter = null,
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get the number of seconds to wait before retrying
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Create a rate limit exception with retry information
     */
    public static function withRetryAfter(int $seconds, string $provider, ?Throwable $previous = null): static
    {
        return new static(
            "Rate limit exceeded for {$provider}. Please try again in {$seconds} seconds.",
            429,
            $previous,
            ['provider' => $provider, 'retry_after' => $seconds],
            $seconds,
        );
    }

    /**
     * Create for reaching the per-minute limit
     */
    public static function perMinute(string $provider, int $limit, ?int $retryAfter = null): static
    {
        $message = "Rate limit of {$limit} requests per minute exceeded for {$provider}";

        if ($retryAfter !== null) {
            $message .= ". Retry in {$retryAfter} seconds.";
        }

        return new static(
            $message,
            429,
            null,
            ['provider' => $provider, 'limit' => $limit, 'period' => 'minute'],
            $retryAfter,
        );
    }

    /**
     * Create for reaching the per-hour limit
     */
    public static function perHour(string $provider, int $limit, ?int $retryAfter = null): static
    {
        $message = "Rate limit of {$limit} requests per hour exceeded for {$provider}";

        if ($retryAfter !== null) {
            $message .= ". Retry in {$retryAfter} seconds.";
        }

        return new static(
            $message,
            429,
            null,
            ['provider' => $provider, 'limit' => $limit, 'period' => 'hour'],
            $retryAfter,
        );
    }
}
