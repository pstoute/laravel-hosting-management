<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Support\Collection;
use Pstoute\LaravelHosting\Data\Backup;
use Pstoute\LaravelHosting\Data\ConnectionResult;
use Pstoute\LaravelHosting\Data\Server;
use Pstoute\LaravelHosting\Data\ServerMetrics;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Data\SslCertificate;
use Pstoute\LaravelHosting\Data\SystemUser;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\ServerProvider;
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;
use Pstoute\LaravelHosting\Exceptions\ServerNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;

class GridPaneProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 10;

    public function getName(): string
    {
        return 'gridpane';
    }

    public function getDisplayName(): string
    {
        return 'GridPane';
    }

    protected function getDefaultApiUrl(): string
    {
        return 'https://my.gridpane.com/api/v1';
    }

    public function getCapabilities(): array
    {
        return [
            Capability::ServerManagement,
            Capability::ServerProvisioning,
            Capability::CustomServer,
            Capability::SystemUserManagement,
            Capability::SiteProvisioning,
            Capability::SiteSuspension,
            Capability::SslInstallation,
            Capability::SslAutoRenewal,
            Capability::BackupCreation,
            Capability::BackupRestore,
            Capability::PhpVersionSwitching,
            Capability::CacheClearing,
            Capability::WordPressManagement,
            Capability::StagingSites,
        ];
    }

    public function testConnection(): ConnectionResult
    {
        if (!$this->isConfigured()) {
            return ConnectionResult::failure('API token not configured');
        }

        $startTime = microtime(true);

        try {
            $response = $this->request('get', '/server');
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            return ConnectionResult::success(
                'Connection successful',
                ['server_count' => count($response['data'] ?? [])],
                $latencyMs
            );
        } catch (\Exception $e) {
            return ConnectionResult::failure($e->getMessage());
        }
    }

    protected function getTestConnectionEndpoint(): string
    {
        return 'server';
    }

    public function listServers(): Collection
    {
        return $this->cached('servers', function () {
            $response = $this->request('get', '/server');
            $servers = $response['data'] ?? [];

            return collect($servers)->map(fn (array $data) => $this->mapServer($data));
        });
    }

    public function getServer(string $serverId): Server
    {
        try {
            $response = $this->request('get', "/server/{$serverId}");
            return $this->mapServer($response['data'] ?? []);
        } catch (\Exception $e) {
            throw new ServerNotFoundException($serverId, "Server not found: {$e->getMessage()}");
        }
    }

    public function createServer(array $config): Server
    {
        $this->ensureCapability(Capability::ServerProvisioning);

        $payload = [
            'name' => $config['name'],
            'provider' => $config['provider'] ?? 'digitalocean',
            'region' => $config['region'] ?? 'nyc3',
            'size' => $config['size'] ?? 's-1vcpu-1gb',
            'webserver' => $config['webserver'] ?? 'nginx',
            'database' => $config['database_type'] ?? 'percona',
        ];

        $response = $this->request('post', '/server', $payload);
        $this->clearCache('servers');

        return $this->mapServer($response['data'] ?? []);
    }

    public function deleteServer(string $serverId): bool
    {
        $this->request('delete', "/server/{$serverId}");
        $this->clearCache('servers');
        return true;
    }

    public function rebootServer(string $serverId): bool
    {
        $this->request('post', "/server/{$serverId}/reboot");
        return true;
    }

    public function getServerMetrics(string $serverId): ServerMetrics
    {
        $this->ensureCapability(Capability::ResourceMonitoring);

        $response = $this->request('get', "/server/{$serverId}/monitor");
        $data = $response['data'] ?? [];

        return new ServerMetrics(
            cpuUsage: $data['cpu'] ?? null,
            memoryUsage: $data['memory'] ?? null,
            diskUsage: $data['disk'] ?? null,
        );
    }

    public function listSystemUsers(string $serverId): Collection
    {
        $this->ensureCapability(Capability::SystemUserManagement);

        return $this->cached("system_users:{$serverId}", function () use ($serverId) {
            $response = $this->request('get', "/server/{$serverId}/system-user");
            return collect($response['data'] ?? [])
                ->map(fn (array $data) => $this->mapSystemUser($data, $serverId));
        });
    }

    public function createSystemUser(string $serverId, array $config): SystemUser
    {
        $this->ensureCapability(Capability::SystemUserManagement);

        $response = $this->request('post', "/server/{$serverId}/system-user", [
            'username' => $config['username'],
        ]);

        $this->clearCache("system_users:{$serverId}");

        return $this->mapSystemUser($response['data'] ?? [], $serverId);
    }

    public function deleteSystemUser(string $serverId, string $userId): bool
    {
        $this->ensureCapability(Capability::SystemUserManagement);

        $this->request('delete', "/server/{$serverId}/system-user/{$userId}");
        $this->clearCache("system_users:{$serverId}");

        return true;
    }

    public function listSites(?string $serverId = null): Collection
    {
        $cacheKey = $serverId ? "sites:{$serverId}" : 'sites:all';

        return $this->cached($cacheKey, function () use ($serverId) {
            if ($serverId) {
                $response = $this->request('get', "/server/{$serverId}/site");
                return collect($response['data'] ?? [])
                    ->map(fn (array $data) => $this->mapSite($data, $serverId));
            }

            $allSites = collect();
            $servers = $this->listServers();

            foreach ($servers as $server) {
                try {
                    $response = $this->request('get', "/server/{$server->id}/site");
                    $sites = collect($response['data'] ?? [])
                        ->map(fn (array $data) => $this->mapSite($data, $server->id));
                    $allSites = $allSites->merge($sites);
                } catch (\Exception) {
                    continue;
                }
            }

            return $allSites;
        });
    }

    public function getSite(string $siteId): Site
    {
        $allSites = $this->listSites();
        $site = $allSites->first(fn (Site $s) => $s->id === $siteId);

        if (!$site) {
            throw new SiteNotFoundException($siteId);
        }

        return $site;
    }

    public function createSite(string $serverId, array $config): Site
    {
        $this->ensureCapability(Capability::SiteProvisioning);

        $payload = [
            'url' => $config['domain'],
            'site_user' => $config['site_user'] ?? null,
            'php_version' => $this->formatPhpVersion($config['php_version'] ?? '8.3'),
        ];

        if ($config['install_wordpress'] ?? false) {
            $payload['wordpress'] = true;
            $payload['wp_admin_email'] = $config['admin_email'] ?? null;
            $payload['wp_admin_user'] = $config['admin_user'] ?? 'admin';
            $payload['wp_admin_password'] = $config['admin_password'] ?? null;
        }

        $response = $this->request('post', "/server/{$serverId}/site", $payload);

        $this->clearCache("sites:{$serverId}");
        $this->clearCache('sites:all');

        return $this->mapSite($response['data'] ?? [], $serverId);
    }

    public function deleteSite(string $siteId): bool
    {
        $site = $this->getSite($siteId);
        $this->request('delete', "/server/{$site->serverId}/site/{$siteId}");

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function suspendSite(string $siteId): bool
    {
        $this->ensureCapability(Capability::SiteSuspension);

        $site = $this->getSite($siteId);
        $this->request('post', "/site/{$siteId}/disable");

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function unsuspendSite(string $siteId): bool
    {
        $this->ensureCapability(Capability::SiteSuspension);

        $site = $this->getSite($siteId);
        $this->request('post', "/site/{$siteId}/enable");

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->ensureCapability(Capability::PhpVersionSwitching);

        $site = $this->getSite($siteId);
        $this->request('post', "/site/{$siteId}/php-version", [
            'php_version' => $this->formatPhpVersion($version->value),
        ]);

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);
        $response = $this->request('post', "/site/{$siteId}/ssl");

        return new SslCertificate(
            id: (string) ($response['data']['id'] ?? uniqid('ssl_')),
            siteId: $siteId,
            status: SslStatus::Installing,
            provider: 'letsencrypt',
            autoRenewal: true,
            domains: [$site->domain],
        );
    }

    public function listBackups(string $siteId): Collection
    {
        $this->ensureCapability(Capability::BackupCreation);

        $response = $this->request('get', "/site/{$siteId}/backup");

        return collect($response['data'] ?? [])
            ->map(fn (array $data) => Backup::fromArray([
                'id' => $data['id'] ?? '',
                'site_id' => $siteId,
                'status' => $data['status'] ?? 'completed',
                'type' => $data['type'] ?? 'full',
                'size_bytes' => $data['size'] ?? null,
                'created_at' => $data['created_at'] ?? null,
            ]));
    }

    public function createBackup(string $siteId, array $options = []): Backup
    {
        $this->ensureCapability(Capability::BackupCreation);

        $response = $this->request('post', "/site/{$siteId}/backup", [
            'type' => $options['type'] ?? 'full',
        ]);

        return Backup::fromArray([
            'id' => $response['data']['id'] ?? uniqid('backup_'),
            'site_id' => $siteId,
            'status' => 'pending',
            'type' => $options['type'] ?? 'full',
        ]);
    }

    public function restoreBackup(string $siteId, string $backupId): bool
    {
        $this->ensureCapability(Capability::BackupRestore);

        $this->request('post', "/site/{$siteId}/backup/{$backupId}/restore");
        return true;
    }

    public function clearCache(string $siteId): bool
    {
        $this->ensureCapability(Capability::CacheClearing);

        $this->request('post', "/site/{$siteId}/cache/clear");
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Mapping Methods
    |--------------------------------------------------------------------------
    */

    protected function mapServer(array $data): Server
    {
        return new Server(
            id: (string) ($data['id'] ?? ''),
            name: $data['name'] ?? '',
            status: ServerStatus::fromString($data['status'] ?? null),
            ipAddress: $data['ip'] ?? null,
            serverProvider: ServerProvider::fromString($data['datacenter_provider'] ?? null),
            region: $data['datacenter'] ?? null,
            size: $data['size'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSite(array $data, string $serverId): Site
    {
        return new Site(
            id: (string) ($data['id'] ?? ''),
            serverId: $serverId,
            domain: $data['url'] ?? $data['domain'] ?? '',
            status: SiteStatus::fromString($data['status'] ?? null),
            phpVersion: PhpVersion::fromString($data['php_version'] ?? null),
            sslEnabled: !empty($data['ssl_enabled']) || !empty($data['https']),
            sslStatus: !empty($data['ssl_enabled']) ? SslStatus::Active : SslStatus::None,
            systemUser: $data['site_user'] ?? null,
            isWordPress: !empty($data['wordpress']) || !empty($data['is_wordpress']),
            isStaging: !empty($data['staging']),
            productionSiteId: $data['production_site_id'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSystemUser(array $data, string $serverId): SystemUser
    {
        return new SystemUser(
            id: (string) ($data['id'] ?? ''),
            username: $data['username'] ?? $data['name'] ?? '',
            serverId: $serverId,
            isIsolated: true,
            hasSshAccess: !empty($data['ssh_enabled']),
            homeDirectory: $data['home'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function formatPhpVersion(string $version): string
    {
        return str_replace('.', '', $version);
    }
}
