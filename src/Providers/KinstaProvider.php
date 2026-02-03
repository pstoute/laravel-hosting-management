<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Support\Collection;
use Pstoute\LaravelHosting\Data\Backup;
use Pstoute\LaravelHosting\Data\ConnectionResult;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Data\SslCertificate;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;

class KinstaProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 60;

    public function getName(): string
    {
        return 'kinsta';
    }

    public function getDisplayName(): string
    {
        return 'Kinsta';
    }

    protected function getDefaultApiUrl(): string
    {
        return 'https://api.kinsta.com/v2';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiToken) && !empty($this->config['company_id']);
    }

    public function getCapabilities(): array
    {
        return [
            Capability::SiteProvisioning,
            Capability::SslInstallation,
            Capability::SslAutoRenewal,
            Capability::BackupCreation,
            Capability::BackupRestore,
            Capability::PhpVersionSwitching,
            Capability::CacheClearing,
            Capability::StagingSites,
            Capability::WordPressManagement,
            Capability::ResourceMonitoring,
        ];
    }

    public function testConnection(): ConnectionResult
    {
        if (!$this->isConfigured()) {
            return ConnectionResult::failure('API key and company ID not configured');
        }

        $startTime = microtime(true);

        try {
            $companyId = $this->config['company_id'];
            $response = $this->request('get', "/sites?company={$companyId}");
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            $sites = $response['company']['sites'] ?? [];

            return ConnectionResult::success(
                'Connection successful',
                ['site_count' => count($sites)],
                $latencyMs
            );
        } catch (\Exception $e) {
            return ConnectionResult::failure($e->getMessage());
        }
    }

    public function listServers(): Collection
    {
        // Kinsta is managed hosting - no traditional servers
        return collect();
    }

    public function listSites(?string $serverId = null): Collection
    {
        return $this->cached('sites', function () {
            $companyId = $this->config['company_id'];
            $response = $this->request('get', "/sites?company={$companyId}");
            $sites = $response['company']['sites'] ?? [];

            return collect($sites)->map(fn (array $data) => $this->mapSite($data));
        });
    }

    public function getSite(string $siteId): Site
    {
        try {
            $response = $this->request('get', "/sites/{$siteId}");
            return $this->mapSite($response['site'] ?? []);
        } catch (\Exception $e) {
            throw new SiteNotFoundException($siteId, null, "Site not found: {$e->getMessage()}");
        }
    }

    public function createSite(string $serverId, array $config): Site
    {
        $this->ensureCapability(Capability::SiteProvisioning);

        $payload = [
            'company' => $this->config['company_id'],
            'display_name' => $config['domain'],
            'region' => $config['region'] ?? 'us-central1',
        ];

        if ($config['install_wordpress'] ?? true) {
            $payload['install_mode'] = 'new';
            $payload['is_subdomain_multisite'] = false;
            $payload['admin_email'] = $config['admin_email'] ?? '';
            $payload['admin_password'] = $config['admin_password'] ?? '';
            $payload['admin_user'] = $config['admin_user'] ?? 'admin';
            $payload['site_title'] = $config['domain'];
            $payload['woocommerce'] = $config['woocommerce'] ?? false;
            $payload['wp_language'] = $config['language'] ?? 'en_US';
        }

        $response = $this->request('post', '/sites', $payload);
        $this->clearCache('sites');

        // Kinsta returns operation info, may need to poll
        return $this->mapSite($response['site'] ?? $response);
    }

    public function deleteSite(string $siteId): bool
    {
        $this->request('delete', "/sites/{$siteId}");
        $this->clearCache('sites');
        return true;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->ensureCapability(Capability::PhpVersionSwitching);

        $site = $this->getSite($siteId);
        $envId = $site->metadata['environment_id'] ?? null;

        if (!$envId) {
            return false;
        }

        $this->request('put', "/sites/environments/{$envId}/php-version", [
            'php_version' => $version->value,
        ]);

        $this->clearCache('sites');
        return true;
    }

    public function getSslCertificate(string $siteId): ?SslCertificate
    {
        $site = $this->getSite($siteId);

        // Kinsta provides free SSL through Cloudflare by default
        if ($site->sslEnabled) {
            return new SslCertificate(
                id: "ssl_{$siteId}",
                siteId: $siteId,
                status: SslStatus::Active,
                provider: 'cloudflare',
                autoRenewal: true,
                domains: [$site->domain],
            );
        }

        return null;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);
        $envId = $site->metadata['environment_id'] ?? null;

        if ($envId) {
            $this->request('post', "/sites/environments/{$envId}/ssl");
        }

        return new SslCertificate(
            id: uniqid('ssl_'),
            siteId: $siteId,
            status: SslStatus::Active,
            provider: 'cloudflare',
            autoRenewal: true,
            domains: [$site->domain],
        );
    }

    public function listBackups(string $siteId): Collection
    {
        $this->ensureCapability(Capability::BackupCreation);

        $site = $this->getSite($siteId);
        $envId = $site->metadata['environment_id'] ?? null;

        if (!$envId) {
            return collect();
        }

        $response = $this->request('get', "/sites/environments/{$envId}/backups");

        return collect($response['environment']['backups'] ?? [])
            ->map(fn (array $data) => Backup::fromArray([
                'id' => $data['id'] ?? '',
                'site_id' => $siteId,
                'status' => $data['status'] ?? 'completed',
                'type' => $data['type'] ?? 'scheduled',
                'description' => $data['note'] ?? null,
                'created_at' => $data['created_at'] ?? null,
            ]));
    }

    public function createBackup(string $siteId, array $options = []): Backup
    {
        $this->ensureCapability(Capability::BackupCreation);

        $site = $this->getSite($siteId);
        $envId = $site->metadata['environment_id'] ?? null;

        if (!$envId) {
            throw new SiteNotFoundException($siteId, null, 'Environment not found');
        }

        $response = $this->request('post', "/sites/environments/{$envId}/manual-backups", [
            'tag' => $options['description'] ?? 'Manual backup via API',
        ]);

        return Backup::fromArray([
            'id' => $response['backup']['id'] ?? uniqid('backup_'),
            'site_id' => $siteId,
            'status' => 'pending',
            'description' => $options['description'] ?? 'Manual backup',
        ]);
    }

    public function restoreBackup(string $siteId, string $backupId): bool
    {
        $this->ensureCapability(Capability::BackupRestore);

        $site = $this->getSite($siteId);
        $envId = $site->metadata['environment_id'] ?? null;

        if (!$envId) {
            return false;
        }

        $this->request('post', "/sites/environments/{$envId}/backups/{$backupId}/restore");
        return true;
    }

    public function clearCache(string $siteId): bool
    {
        $this->ensureCapability(Capability::CacheClearing);

        $site = $this->getSite($siteId);
        $envId = $site->metadata['environment_id'] ?? null;

        if (!$envId) {
            return false;
        }

        $this->request('post', "/sites/environments/{$envId}/clear-cache");
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Mapping Methods
    |--------------------------------------------------------------------------
    */

    protected function mapSite(array $data): Site
    {
        $environments = $data['environments'] ?? [];
        $liveEnv = collect($environments)->first(fn ($env) => ($env['name'] ?? '') === 'live');
        $env = $liveEnv ?? ($environments[0] ?? []);

        $primaryDomain = $env['primary_domain']['name'] ?? $data['display_name'] ?? '';

        return new Site(
            id: (string) ($data['id'] ?? ''),
            serverId: '', // Kinsta doesn't have traditional servers
            domain: $primaryDomain,
            status: SiteStatus::Active,
            phpVersion: PhpVersion::fromString($env['php_version'] ?? null),
            sslEnabled: $env['is_ssl'] ?? true,
            sslStatus: ($env['is_ssl'] ?? true) ? SslStatus::Active : SslStatus::None,
            isWordPress: true, // Kinsta is WordPress-only
            isStaging: ($env['name'] ?? '') === 'staging',
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: array_merge($data, ['environment_id' => $env['id'] ?? null]),
        );
    }
}
