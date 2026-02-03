<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Exceptions;

use Throwable;

class SiteNotFoundException extends HostingException
{
    protected string $siteId;
    protected ?string $serverId;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $siteId,
        ?string $serverId = null,
        string $message = '',
        int $code = 404,
        ?Throwable $previous = null,
        array $context = [],
    ) {
        $this->siteId = $siteId;
        $this->serverId = $serverId;

        if ($message === '') {
            $message = $serverId
                ? "Site {$siteId} not found on server {$serverId}"
                : "Site not found: {$siteId}";
        }

        $context['site_id'] = $siteId;
        if ($serverId) {
            $context['server_id'] = $serverId;
        }

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Get the site ID that was not found
     */
    public function getSiteId(): string
    {
        return $this->siteId;
    }

    /**
     * Get the server ID where the site was not found
     */
    public function getServerId(): ?string
    {
        return $this->serverId;
    }

    /**
     * Create for a site that was not found on the provider
     */
    public static function onProvider(string $siteId, string $provider, ?string $serverId = null): static
    {
        return new static(
            $siteId,
            $serverId,
            "Site {$siteId} not found on {$provider}",
            404,
            null,
            ['provider' => $provider],
        );
    }

    /**
     * Create for a site that was not found by domain
     */
    public static function byDomain(string $domain, string $provider): static
    {
        return new static(
            $domain,
            null,
            "Site with domain '{$domain}' not found on {$provider}",
            404,
            null,
            ['provider' => $provider, 'domain' => $domain],
        );
    }
}
