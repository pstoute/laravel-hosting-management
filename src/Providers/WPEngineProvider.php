<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Pstoute\LaravelHosting\Data\Backup;
use Pstoute\LaravelHosting\Data\ConnectionResult;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Data\SslCertificate;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;

class WPEngineProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 60;

    public function getName(): string
    {
        return 'wpengine';
    }

    public function getDisplayName(): string
    {
        return 'WP Engine';
    }

    protected function getDefaultApiUrl(): string
    {
        return 'https://api.wpengineapi.com/v1';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['username']) && !empty($this->config['password']);
    }

    protected function httpClient(): PendingRequest
    {
        return Http::withBasicAuth(
            $this->config['username'] ?? '',
            $this->config['password'] ?? ''
        )->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    public function getCapabilities(): array
    {
        return [
            Capability::SiteProvisioning,
            Capability::SslInstallation,
            Capability::SslAutoRenewal,
            Capability::BackupCreation,
            Capability::BackupRestore,
            Capability::CacheClearing,
            Capability::StagingSites,
            Capability::WordPressManagement,
        ];
    }

    public function testConnection(): ConnectionResult
    {
        if (!$this->isConfigured()) {
            return ConnectionResult::failure('API credentials not configured');
        }

        $startTime = microtime(true);

        try {
            $response = $this->request('get', '/installs');
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            return ConnectionResult::success(
                'Connection successful',
                ['install_count' => count($response['results'] ?? [])],
                $latencyMs
            );
        } catch (\Exception $e) {
            return ConnectionResult::failure($e->getMessage());
        }
    }

    protected function getTestConnectionEndpoint(): string
    {
        return 'installs';
    }

    public function listServers(): Collection
    {
        // WP Engine is managed hosting - no traditional servers
        return collect();
    }

    public function listSites(?string $serverId = null): Collection
    {
        return $this->cached('sites', function () {
            $response = $this->request('get', '/installs');
            $installs = $response['results'] ?? [];

            return collect($installs)->map(fn (array $data) => $this->mapSite($data));
        });
    }

    public function getSite(string $siteId): Site
    {
        try {
            $response = $this->request('get', "/installs/{$siteId}");
            return $this->mapSite($response);
        } catch (\Exception $e) {
            throw new SiteNotFoundException($siteId, null, "Site not found: {$e->getMessage()}");
        }
    }

    public function createSite(string $serverId, array $config): Site
    {
        $this->ensureCapability(Capability::SiteProvisioning);

        $payload = [
            'name' => $this->sanitizeInstallName($config['domain']),
            'account_id' => $config['account_id'] ?? $this->config['account_id'] ?? null,
            'environment' => $config['environment'] ?? 'production',
        ];

        $response = $this->request('post', '/installs', $payload);
        $this->clearCache('sites');

        return $this->mapSite($response);
    }

    public function deleteSite(string $siteId): bool
    {
        $this->request('delete', "/installs/{$siteId}");
        $this->clearCache('sites');
        return true;
    }

    public function getSslCertificate(string $siteId): ?SslCertificate
    {
        $site = $this->getSite($siteId);

        // WP Engine provides SSL by default
        return new SslCertificate(
            id: "ssl_{$siteId}",
            siteId: $siteId,
            status: SslStatus::Active,
            provider: 'wpengine',
            autoRenewal: true,
            domains: [$site->domain],
        );
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        // WP Engine manages SSL automatically
        return $this->getSslCertificate($siteId) ?? new SslCertificate(
            id: uniqid('ssl_'),
            siteId: $siteId,
            status: SslStatus::Active,
            provider: 'wpengine',
            autoRenewal: true,
        );
    }

    public function listBackups(string $siteId): Collection
    {
        $this->ensureCapability(Capability::BackupCreation);

        $response = $this->request('get', "/installs/{$siteId}/backups");

        return collect($response['results'] ?? [])
            ->map(fn (array $data) => Backup::fromArray([
                'id' => $data['id'] ?? '',
                'site_id' => $siteId,
                'status' => $data['status'] ?? 'completed',
                'description' => $data['description'] ?? null,
                'created_at' => $data['created_at'] ?? null,
            ]));
    }

    public function createBackup(string $siteId, array $options = []): Backup
    {
        $this->ensureCapability(Capability::BackupCreation);

        $response = $this->request('post', "/installs/{$siteId}/backups", [
            'description' => $options['description'] ?? 'Manual backup via API',
            'notification_emails' => $options['notification_emails'] ?? [],
        ]);

        return Backup::fromArray([
            'id' => $response['id'] ?? uniqid('backup_'),
            'site_id' => $siteId,
            'status' => 'pending',
            'description' => $options['description'] ?? 'Manual backup',
        ]);
    }

    public function restoreBackup(string $siteId, string $backupId): bool
    {
        $this->ensureCapability(Capability::BackupRestore);

        $this->request('post', "/installs/{$siteId}/backups/{$backupId}/restore");
        return true;
    }

    public function clearCache(string $siteId): bool
    {
        $this->ensureCapability(Capability::CacheClearing);

        $this->request('post', "/installs/{$siteId}/purge_cache");
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    protected function sanitizeInstallName(string $name): string
    {
        // WP Engine install names must be lowercase alphanumeric, max 14 chars
        $sanitized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name) ?? $name);
        return substr($sanitized, 0, 14);
    }

    /*
    |--------------------------------------------------------------------------
    | Mapping Methods
    |--------------------------------------------------------------------------
    */

    protected function mapSite(array $data): Site
    {
        $primaryDomain = $data['primary_domain'] ?? "{$data['name']}.wpengine.com";

        return new Site(
            id: (string) ($data['id'] ?? $data['name'] ?? ''),
            serverId: '', // WP Engine doesn't have traditional servers
            domain: $primaryDomain,
            status: SiteStatus::fromString($data['status'] ?? 'active'),
            phpVersion: PhpVersion::fromString($data['php_version'] ?? null),
            sslEnabled: true, // WP Engine always has SSL
            sslStatus: SslStatus::Active,
            isWordPress: true, // WP Engine is WordPress-only
            isStaging: ($data['environment'] ?? 'production') !== 'production',
            createdAt: isset($data['created_date']) ? new \DateTimeImmutable($data['created_date']) : null,
            metadata: $data,
        );
    }
}
