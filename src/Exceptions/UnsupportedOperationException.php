<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Exceptions;

use Pstoute\LaravelHosting\Enums\Capability;

class UnsupportedOperationException extends HostingException
{
    protected ?string $provider = null;
    protected ?Capability $capability = null;
    protected ?string $operation = null;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'Operation not supported',
        int $code = 501,
        ?string $provider = null,
        ?Capability $capability = null,
        ?string $operation = null,
        array $context = [],
    ) {
        parent::__construct($message, $code, null, $context);
        $this->provider = $provider;
        $this->capability = $capability;
        $this->operation = $operation;
    }

    /**
     * Get the provider that doesn't support the operation
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get the capability that is not supported
     */
    public function getCapability(): ?Capability
    {
        return $this->capability;
    }

    /**
     * Get the operation name that is not supported
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Create for a capability that is not supported by the provider
     */
    public static function capability(Capability $capability, string $provider): static
    {
        return new static(
            "The capability '{$capability->label()}' is not supported by {$provider}",
            501,
            $provider,
            $capability,
            null,
            ['capability' => $capability->value, 'provider' => $provider],
        );
    }

    /**
     * Create for an operation that is not supported by the provider
     */
    public static function operation(string $operation, string $provider): static
    {
        return new static(
            "The operation '{$operation}' is not supported by {$provider}",
            501,
            $provider,
            null,
            $operation,
            ['operation' => $operation, 'provider' => $provider],
        );
    }

    /**
     * Create for an operation that is not implemented yet
     */
    public static function notImplemented(string $operation, string $provider): static
    {
        return new static(
            "The operation '{$operation}' is not yet implemented for {$provider}",
            501,
            $provider,
            null,
            $operation,
            ['operation' => $operation, 'provider' => $provider, 'not_implemented' => true],
        );
    }
}
