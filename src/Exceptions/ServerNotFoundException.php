<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Exceptions;

use Throwable;

class ServerNotFoundException extends HostingException
{
    protected string $serverId;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $serverId,
        string $message = '',
        int $code = 404,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $this->serverId = $serverId;

        if ($message === '') {
            $message = "Server not found: {$serverId}";
        }

        $context['server_id'] = $serverId;

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Get the server ID that was not found
     */
    public function getServerId(): string
    {
        return $this->serverId;
    }

    /**
     * Create for a server that was not found on the provider
     */
    public static function onProvider(string $serverId, string $provider): static
    {
        return new static(
            $serverId,
            "Server {$serverId} not found on {$provider}",
            404,
            null,
            ['provider' => $provider],
        );
    }
}
