<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

class SiteSuspended extends HostingEvent
{
    public function __construct(
        string $provider,
        ?string $serverId,
        string $siteId,
        public readonly ?string $domain = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($provider, $serverId, $siteId);
    }
}
