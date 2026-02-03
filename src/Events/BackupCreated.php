<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

use Pstoute\LaravelHosting\Data\Backup;

class BackupCreated extends HostingEvent
{
    public function __construct(
        string $provider,
        ?string $serverId,
        string $siteId,
        public readonly Backup $backup,
    ) {
        parent::__construct($provider, $serverId, $siteId);
    }
}
