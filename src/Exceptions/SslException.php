<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Exceptions;

use Throwable;

class SslException extends HostingException
{
    protected ?string $siteId = null;
    protected ?string $domain = null;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'SSL operation failed',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        ?string $siteId = null,
        ?string $domain = null,
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->siteId = $siteId;
        $this->domain = $domain;
    }

    /**
     * Get the site ID associated with the SSL error
     */
    public function getSiteId(): ?string
    {
        return $this->siteId;
    }

    /**
     * Get the domain associated with the SSL error
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Create for a failed SSL installation
     */
    public static function installationFailed(string $siteId, string $reason, ?string $domain = null, ?Throwable $previous = null): static
    {
        return new static(
            "SSL installation failed for site {$siteId}: {$reason}",
            0,
            $previous,
            ['site_id' => $siteId, 'reason' => $reason, 'domain' => $domain],
            $siteId,
            $domain,
        );
    }

    /**
     * Create for a domain validation failure
     */
    public static function validationFailed(string $domain, string $reason, ?string $siteId = null): static
    {
        return new static(
            "SSL validation failed for {$domain}: {$reason}",
            0,
            null,
            ['domain' => $domain, 'site_id' => $siteId, 'reason' => $reason],
            $siteId,
            $domain,
        );
    }

    /**
     * Create for an invalid custom certificate
     */
    public static function invalidCertificate(string $reason, ?string $siteId = null): static
    {
        return new static(
            "Invalid SSL certificate: {$reason}",
            0,
            null,
            ['reason' => $reason, 'site_id' => $siteId],
            $siteId,
        );
    }

    /**
     * Create for a certificate that cannot be renewed
     */
    public static function renewalFailed(string $siteId, string $reason, ?string $domain = null): static
    {
        return new static(
            "SSL renewal failed for site {$siteId}: {$reason}",
            0,
            null,
            ['site_id' => $siteId, 'reason' => $reason, 'domain' => $domain],
            $siteId,
            $domain,
        );
    }
}
