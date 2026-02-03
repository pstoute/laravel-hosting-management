<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

use Pstoute\LaravelHosting\Data\Server;

class ServerCreated extends HostingEvent
{
    public function __construct(
        string $provider,
        public readonly Server $server,
    ) {
        parent::__construct($provider, $server->id);
    }
}
