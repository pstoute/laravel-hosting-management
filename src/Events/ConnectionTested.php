<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

use Pstoute\LaravelHosting\Data\ConnectionResult;

class ConnectionTested extends HostingEvent
{
    public function __construct(
        string $provider,
        public readonly ConnectionResult $result,
    ) {
        parent::__construct($provider);
    }
}
