<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

class ServerRebooted extends HostingEvent
{
    public function __construct(
        string $provider,
        string $serverId,
        public readonly ?string $serverName = null,
    ) {
        parent::__construct($provider, $serverId);
    }
}
