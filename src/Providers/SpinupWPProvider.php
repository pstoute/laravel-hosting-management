<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Support\Collection;
use Pstoute\LaravelHosting\Data\Backup;
use Pstoute\LaravelHosting\Data\Server;
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

class SpinupWPProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 60;

    public function getName(): string
    {
        return 'spinupwp';
    }

    public function getDisplayName(): string
    {
        return 'SpinupWP';
    }

    protected function getDefaultApiUrl(): string
    {
        return 'https://api.spinupwp.com/v1';
    }

    public function getCapabilities(): array
    {
        return [
            Capability::ServerManagement,
            Capability::ServerProvisioning,
            Capability::SystemUserManagement,
            Capability::SiteProvisioning,
            Capability::SslInstallation,
            Capability::SslAutoRenewal,
            Capability::BackupCreation,
            Capability::BackupRestore,
            Capability::PhpVersionSwitching,
            Capability::CacheClearing,
            Capability::StagingSites,
            Capability::WordPressManagement,
        ];
    }

    public function listServers(): Collection
    {
        return $this->cached('servers', function () {
            $response = $this->request('get', '/servers');
            $servers = $response['data'] ?? [];

            return collect($servers)->map(fn (array $data) => $this->mapServer($data));
        });
    }

    public function getServer(string $serverId): Server
    {
        try {
            $response = $this->request('get', "/servers/{$serverId}");
            return $this->mapServer($response['data'] ?? $response);
        } catch (\Exception $e) {
            throw new ServerNotFoundException($serverId, "Server not found: {$e->getMessage()}");
        }
    }

    public function createServer(array $config): Server
    {
        $this->ensureCapability(Capability::ServerProvisioning);

        $payload = [
            'name' => $config['name'],
            'provider_name' => $config['provider'] ?? 'digitalocean',
            'region' => $config['region'] ?? 'nyc3',
            'size' => $config['size'] ?? 's-1vcpu-1gb',
            'database' => $config['database_type'] ?? 'mysql-8.0',
        ];

        $response = $this->request('post', '/servers', $payload);
        $this->clearCache('servers');

        return $this->mapServer($response['data'] ?? []);
    }

    public function deleteServer(string $serverId): bool
    {
        $this->request('delete', "/servers/{$serverId}");
        $this->clearCache('servers');
        return true;
    }

    public function rebootServer(string $serverId): bool
    {
        $this->request('post', "/servers/{$serverId}/reboot");
        return true;
    }

    public function listSystemUsers(string $serverId): Collection
    {
        $this->ensureCapability(Capability::SystemUserManagement);

        return $this->cached("system_users:{$serverId}", function () use ($serverId) {
            $response = $this->request('get', "/servers/{$serverId}/users");
            return collect($response['data'] ?? [])
                ->map(fn (array $data) => $this->mapSystemUser($data, $serverId));
        });
    }

    public function createSystemUser(string $serverId, array $config): SystemUser
    {
        $this->ensureCapability(Capability::SystemUserManagement);

        $response = $this->request('post', "/servers/{$serverId}/users", [
            'username' => $config['username'],
        ]);

        $this->clearCache("system_users:{$serverId}");

        return $this->mapSystemUser($response['data'] ?? [], $serverId);
    }

    public function deleteSystemUser(string $serverId, string $userId): bool
    {
        $this->ensureCapability(Capability::SystemUserManagement);

        $this->request('delete', "/servers/{$serverId}/users/{$userId}");
        $this->clearCache("system_users:{$serverId}");

        return true;
    }

    public function listSites(?string $serverId = null): Collection
    {
        $cacheKey = $serverId ? "sites:{$serverId}" : 'sites:all';

        return $this->cached($cacheKey, function () use ($serverId) {
            if ($serverId) {
                $response = $this->request('get', "/servers/{$serverId}/sites");
                return collect($response['data'] ?? [])
                    ->map(fn (array $data) => $this->mapSite($data, $serverId));
            }

            // SpinupWP has a sites endpoint that returns all sites
            $response = $this->request('get', '/sites');
            return collect($response['data'] ?? [])
                ->map(fn (array $data) => $this->mapSite($data, (string) ($data['server_id'] ?? '')));
        });
    }

    public function getSite(string $siteId): Site
    {
        try {
            $response = $this->request('get', "/sites/{$siteId}");
            $data = $response['data'] ?? $response;
            return $this->mapSite($data, (string) ($data['server_id'] ?? ''));
        } catch (\Exception $e) {
            throw new SiteNotFoundException($siteId, null, "Site not found: {$e->getMessage()}");
        }
    }

    public function createSite(string $serverId, array $config): Site
    {
        $this->ensureCapability(Capability::SiteProvisioning);

        $payload = [
            'domain' => $config['domain'],
            'site_user' => $config['site_user'] ?? null,
            'php_version' => $config['php_version'] ?? '8.3',
            'page_cache' => [
                'enabled' => $config['page_cache'] ?? true,
            ],
        ];

        if ($config['install_wordpress'] ?? true) {
            $payload['wordpress'] = [
                'title' => $config['domain'],
                'admin_email' => $config['admin_email'] ?? '',
                'admin_user' => $config['admin_user'] ?? 'admin',
                'admin_password' => $config['admin_password'] ?? '',
            ];
        }

        if ($config['install_ssl'] ?? true) {
            $payload['https'] = [
                'enabled' => true,
            ];
        }

        $response = $this->request('post', "/servers/{$serverId}/sites", $payload);

        $this->clearCache("sites:{$serverId}");
        $this->clearCache('sites:all');

        return $this->mapSite($response['data'] ?? [], $serverId);
    }

    public function deleteSite(string $siteId): bool
    {
        $site = $this->getSite($siteId);
        $this->request('delete', "/sites/{$siteId}");

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function suspendSite(string $siteId): bool
    {
        $this->ensureCapability(Capability::SiteSuspension);

        $this->request('post', "/sites/{$siteId}/disable");
        return true;
    }

    public function unsuspendSite(string $siteId): bool
    {
        $this->ensureCapability(Capability::SiteSuspension);

        $this->request('post', "/sites/{$siteId}/enable");
        return true;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->ensureCapability(Capability::PhpVersionSwitching);

        $this->request('patch', "/sites/{$siteId}", [
            'php_version' => $version->value,
        ]);

        $this->clearCache('sites:all');
        return true;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);
        $response = $this->request('post', "/sites/{$siteId}/https");

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

        $response = $this->request('get', "/sites/{$siteId}/backups");

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

        $response = $this->request('post', "/sites/{$siteId}/backups");

        return Backup::fromArray([
            'id' => $response['data']['id'] ?? uniqid('backup_'),
            'site_id' => $siteId,
            'status' => 'pending',
        ]);
    }

    public function restoreBackup(string $siteId, string $backupId): bool
    {
        $this->ensureCapability(Capability::BackupRestore);

        $this->request('post', "/sites/{$siteId}/backups/{$backupId}/restore");
        return true;
    }

    public function clearCache(string $siteId): bool
    {
        $this->ensureCapability(Capability::CacheClearing);

        $this->request('delete', "/sites/{$siteId}/page-cache");
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
            ipAddress: $data['ip_address'] ?? null,
            serverProvider: ServerProvider::fromString($data['provider_name'] ?? null),
            region: $data['region'] ?? null,
            size: $data['size'] ?? null,
            ubuntuVersion: $data['ubuntu_version'] ?? null,
            databaseType: $data['database'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSite(array $data, string $serverId): Site
    {
        $httpsEnabled = !empty($data['https']['enabled']);

        return new Site(
            id: (string) ($data['id'] ?? ''),
            serverId: $serverId ?: (string) ($data['server_id'] ?? ''),
            domain: $data['domain'] ?? '',
            status: SiteStatus::fromString($data['status'] ?? null),
            phpVersion: PhpVersion::fromString($data['php_version'] ?? null),
            sslEnabled: $httpsEnabled,
            sslStatus: $httpsEnabled ? SslStatus::Active : SslStatus::None,
            systemUser: $data['site_user'] ?? null,
            isWordPress: true, // SpinupWP is WordPress-only
            isStaging: !empty($data['is_staging']),
            productionSiteId: $data['production_site_id'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSystemUser(array $data, string $serverId): SystemUser
    {
        return new SystemUser(
            id: (string) ($data['id'] ?? ''),
            username: $data['username'] ?? '',
            serverId: $serverId,
            isIsolated: true,
            hasSshAccess: !empty($data['ssh_enabled']),
            homeDirectory: $data['home_directory'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }
}
