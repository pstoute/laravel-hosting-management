<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Events;

use DateTimeImmutable;
use Pstoute\LaravelHosting\Data\SslCertificate;

class SslCertificateExpiring extends HostingEvent
{
    public function __construct(
        string $provider,
        ?string $serverId,
        string $siteId,
        public readonly SslCertificate $certificate,
        public readonly int $daysUntilExpiration,
    ) {
        parent::__construct($provider, $serverId, $siteId);
    }
}
