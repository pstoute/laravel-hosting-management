<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

use Pstoute\LaravelHosting\Data\SslCertificate;

class SslCertificateInstalled extends HostingEvent
{
    public function __construct(
        string $provider,
        ?string $serverId,
        string $siteId,
        public readonly SslCertificate $certificate,
    ) {
        parent::__construct($provider, $serverId, $siteId);
    }
}
