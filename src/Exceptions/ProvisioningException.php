<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Exceptions;

use Throwable;

class ProvisioningException extends HostingException
{
    protected ?string $resourceType = null;
    protected ?string $resourceId = null;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'Provisioning failed',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        ?string $resourceType = null,
        ?string $resourceId = null,
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
    }

    /**
     * Get the type of resource that failed provisioning
     */
    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    /**
     * Get the ID of the resource that failed provisioning
     */
    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    /**
     * Create for a failed server provisioning
     */
    public static function serverFailed(string $message, ?string $serverId = null, ?Throwable $previous = null): static
    {
        return new static(
            $message,
            0,
            $previous,
            ['server_id' => $serverId],
            'server',
            $serverId,
        );
    }

    /**
     * Create for a failed site provisioning
     */
    public static function siteFailed(string $message, ?string $siteId = null, ?Throwable $previous = null): static
    {
        return new static(
            $message,
            0,
            $previous,
            ['site_id' => $siteId],
            'site',
            $siteId,
        );
    }

    /**
     * Create for a failed database provisioning
     */
    public static function databaseFailed(string $message, ?string $databaseId = null, ?Throwable $previous = null): static
    {
        return new static(
            $message,
            0,
            $previous,
            ['database_id' => $databaseId],
            'database',
            $databaseId,
        );
    }

    /**
     * Create for a timeout during provisioning
     */
    public static function timeout(string $resourceType, ?string $resourceId = null): static
    {
        return new static(
            "Provisioning timeout for {$resourceType}" . ($resourceId ? " ({$resourceId})" : ''),
            0,
            null,
            ['resource_type' => $resourceType, 'resource_id' => $resourceId],
            $resourceType,
            $resourceId,
        );
    }

    /**
     * Create for invalid provisioning configuration
     *
     * @param array<string> $errors
     */
    public static function invalidConfiguration(string $resourceType, array $errors): static
    {
        return new static(
            "Invalid {$resourceType} configuration: " . implode(', ', $errors),
            0,
            null,
            ['resource_type' => $resourceType, 'errors' => $errors],
            $resourceType,
        );
    }
}
