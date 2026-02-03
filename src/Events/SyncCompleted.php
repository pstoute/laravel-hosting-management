<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

class SyncCompleted extends HostingEvent
{
    public function __construct(
        string $provider,
        public readonly string $resourceType,
        public readonly int $syncedCount,
        public readonly int $createdCount = 0,
        public readonly int $updatedCount = 0,
        public readonly int $deletedCount = 0,
    ) {
        parent::__construct($provider);
    }
}
