<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Exceptions;

use Throwable;

class AuthenticationException extends HostingException
{
    protected ?string $provider = null;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'Authentication failed',
        int $code = 401,
        ?Throwable $previous = null,
        array $context = [],
        ?string $provider = null,
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->provider = $provider;
    }

    /**
     * Get the provider that failed authentication
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Create for an invalid API token
     */
    public static function invalidToken(string $provider, ?Throwable $previous = null): static
    {
        return new static(
            "Invalid API token for {$provider}",
            401,
            $previous,
            ['provider' => $provider],
            $provider,
        );
    }

    /**
     * Create for an expired API token
     */
    public static function expiredToken(string $provider, ?Throwable $previous = null): static
    {
        return new static(
            "API token for {$provider} has expired",
            401,
            $previous,
            ['provider' => $provider],
            $provider,
        );
    }

    /**
     * Create for missing credentials
     */
    public static function missingCredentials(string $provider): static
    {
        return new static(
            "Missing API credentials for {$provider}",
            401,
            null,
            ['provider' => $provider],
            $provider,
        );
    }

    /**
     * Create for insufficient permissions
     */
    public static function insufficientPermissions(string $provider, ?string $permission = null): static
    {
        $message = $permission
            ? "Insufficient permissions for {$provider}: missing '{$permission}'"
            : "Insufficient permissions for {$provider}";

        return new static(
            $message,
            403,
            null,
            ['provider' => $provider, 'permission' => $permission],
            $provider,
        );
    }
}
