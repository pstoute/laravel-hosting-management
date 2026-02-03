<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Pstoute\LaravelHosting\Data\Database;
use Pstoute\LaravelHosting\Data\DatabaseUser;
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

class RunCloudProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 60;

    public function getName(): string
    {
        return 'runcloud';
    }

    public function getDisplayName(): string
    {
        return 'RunCloud';
    }

    protected function getDefaultApiUrl(): string
    {
        return 'https://manage.runcloud.io/api/v2';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['api_key']) && !empty($this->config['api_secret']);
    }

    protected function httpClient(): PendingRequest
    {
        return Http::withBasicAuth(
            $this->config['api_key'] ?? '',
            $this->config['api_secret'] ?? ''
        )->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    public function getCapabilities(): array
    {
        return [
            Capability::ServerManagement,
            Capability::CustomServer,
            Capability::SystemUserManagement,
            Capability::SiteProvisioning,
            Capability::SslInstallation,
            Capability::SslAutoRenewal,
            Capability::DatabaseManagement,
            Capability::PhpVersionSwitching,
            Capability::GitDeployment,
            Capability::DeploymentScripts,
            Capability::CacheClearing,
            Capability::StagingSites,
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
            'password' => $config['password'] ?? '',
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
                $response = $this->request('get', "/servers/{$serverId}/webapps");
                return collect($response['data'] ?? [])
                    ->map(fn (array $data) => $this->mapSite($data, $serverId));
            }

            $allSites = collect();
            $servers = $this->listServers();

            foreach ($servers as $server) {
                try {
                    $response = $this->request('get', "/servers/{$server->id}/webapps");
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
            'name' => $config['domain'],
            'user' => $config['site_user'] ?? null,
            'type' => $config['project_type'] ?? 'php',
            'publicPath' => $config['public_path'] ?? '/public',
            'phpVersion' => $config['php_version'] ?? 'php83rc',
        ];

        $response = $this->request('post', "/servers/{$serverId}/webapps", $payload);

        $this->clearCache("sites:{$serverId}");
        $this->clearCache('sites:all');

        return $this->mapSite($response['data'] ?? [], $serverId);
    }

    public function deleteSite(string $siteId): bool
    {
        $site = $this->getSite($siteId);
        $this->request('delete', "/servers/{$site->serverId}/webapps/{$siteId}");

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->ensureCapability(Capability::PhpVersionSwitching);

        $site = $this->getSite($siteId);
        $this->request('patch', "/servers/{$site->serverId}/webapps/{$siteId}", [
            'phpVersion' => $this->formatPhpVersion($version->value),
        ]);

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function listDatabases(string $serverId): Collection
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        return $this->cached("databases:{$serverId}", function () use ($serverId) {
            $response = $this->request('get', "/servers/{$serverId}/databases");
            return collect($response['data'] ?? [])
                ->map(fn (array $data) => Database::fromArray([
                    'id' => $data['id'],
                    'name' => $data['name'],
                    'server_id' => $serverId,
                    'type' => $data['collation'] ?? 'utf8mb4_unicode_ci',
                ]));
        });
    }

    public function createDatabase(string $serverId, string $name): Database
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $response = $this->request('post', "/servers/{$serverId}/databases", [
            'name' => $name,
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $this->clearCache("databases:{$serverId}");

        return Database::fromArray([
            'id' => $response['data']['id'] ?? '',
            'name' => $name,
            'server_id' => $serverId,
        ]);
    }

    public function deleteDatabase(string $serverId, string $databaseId): bool
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $this->request('delete', "/servers/{$serverId}/databases/{$databaseId}");
        $this->clearCache("databases:{$serverId}");

        return true;
    }

    public function listDatabaseUsers(string $serverId): Collection
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        return $this->cached("database_users:{$serverId}", function () use ($serverId) {
            $response = $this->request('get', "/servers/{$serverId}/databaseusers");
            return collect($response['data'] ?? [])
                ->map(fn (array $data) => DatabaseUser::fromArray([
                    'id' => $data['id'],
                    'username' => $data['username'],
                    'server_id' => $serverId,
                ]));
        });
    }

    public function createDatabaseUser(string $serverId, string $username, string $password): DatabaseUser
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $response = $this->request('post', "/servers/{$serverId}/databaseusers", [
            'username' => $username,
            'password' => $password,
        ]);

        $this->clearCache("database_users:{$serverId}");

        return DatabaseUser::fromArray([
            'id' => $response['data']['id'] ?? '',
            'username' => $username,
            'server_id' => $serverId,
        ]);
    }

    public function deleteDatabaseUser(string $serverId, string $userId): bool
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $this->request('delete', "/servers/{$serverId}/databaseusers/{$userId}");
        $this->clearCache("database_users:{$serverId}");

        return true;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);
        $response = $this->request('post', "/servers/{$site->serverId}/webapps/{$siteId}/ssl/letsencrypt");

        return new SslCertificate(
            id: (string) ($response['data']['id'] ?? uniqid('ssl_')),
            siteId: $siteId,
            status: SslStatus::Installing,
            provider: 'letsencrypt',
            autoRenewal: true,
            domains: [$site->domain],
        );
    }

    public function clearCache(string $siteId): bool
    {
        $this->ensureCapability(Capability::CacheClearing);

        $site = $this->getSite($siteId);
        $this->request('post', "/servers/{$site->serverId}/webapps/{$siteId}/purge");

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
            status: ServerStatus::fromString($data['connected'] ?? false ? 'active' : 'inactive'),
            ipAddress: $data['ipAddress'] ?? null,
            serverProvider: ServerProvider::fromString($data['provider'] ?? null),
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSite(array $data, string $serverId): Site
    {
        return new Site(
            id: (string) ($data['id'] ?? ''),
            serverId: $serverId,
            domain: $data['name'] ?? $data['domain'] ?? '',
            status: SiteStatus::Active,
            phpVersion: PhpVersion::fromString($data['phpVersion'] ?? null),
            sslEnabled: !empty($data['ssl']),
            sslStatus: !empty($data['ssl']) ? SslStatus::Active : SslStatus::None,
            documentRoot: $data['publicPath'] ?? null,
            projectType: $data['type'] ?? null,
            systemUser: $data['user']['username'] ?? null,
            repository: $data['git']['address'] ?? null,
            repositoryBranch: $data['git']['branch'] ?? null,
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
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function formatPhpVersion(string $version): string
    {
        // RunCloud uses format like 'php83rc'
        return 'php' . str_replace('.', '', $version) . 'rc';
    }
}
