<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

use Pstoute\LaravelHosting\Data\Deployment;

class DeploymentFailed extends HostingEvent
{
    public function __construct(
        string $provider,
        ?string $serverId,
        string $siteId,
        public readonly Deployment $deployment,
        public readonly ?string $errorMessage = null,
    ) {
        parent::__construct($provider, $serverId, $siteId);
    }
}
