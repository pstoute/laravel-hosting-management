<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

use Pstoute\LaravelHosting\Data\Site;

class SiteCreated extends HostingEvent
{
    public function __construct(
        string $provider,
        public readonly Site $site,
    ) {
        parent::__construct($provider, $site->serverId, $site->id);
    }
}
