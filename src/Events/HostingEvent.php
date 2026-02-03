<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class HostingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $provider,
        public readonly ?string $serverId = null,
        public readonly ?string $siteId = null,
    ) {}
}
