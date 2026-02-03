<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Support\Collection;
use Pstoute\LaravelHosting\Data\ConnectionResult;
use Pstoute\LaravelHosting\Data\Database;
use Pstoute\LaravelHosting\Data\DatabaseUser;
use Pstoute\LaravelHosting\Data\Deployment;
use Pstoute\LaravelHosting\Data\Server;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Data\SslCertificate;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\DeploymentStatus;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\ServerProvider;
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;
use Pstoute\LaravelHosting\Exceptions\ServerNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;

class PloiProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 60;

    public function getName(): string
    {
        return 'ploi';
    }

    public function getDisplayName(): string
    {
        return 'Ploi';
    }

    protected function getDefaultApiUrl(): string
    {
        return 'https://ploi.io/api';
    }

    public function getCapabilities(): array
    {
        return [
            Capability::ServerManagement,
            Capability::ServerProvisioning,
            Capability::SiteProvisioning,
            Capability::SslInstallation,
            Capability::SslAutoRenewal,
            Capability::DatabaseManagement,
            Capability::PhpVersionSwitching,
            Capability::GitDeployment,
            Capability::DeploymentScripts,
            Capability::QueueWorkers,
            Capability::ScheduledJobs,
            Capability::EnvironmentVariables,
        ];
    }

    public function testConnection(): ConnectionResult
    {
        if (!$this->isConfigured()) {
            return ConnectionResult::failure('API token not configured');
        }

        $startTime = microtime(true);

        try {
            $response = $this->request('get', '/servers');
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
            'region' => $config['region'] ?? 'ams3',
            'plan' => $config['size'] ?? 's-1vcpu-1gb',
            'type' => $config['type'] ?? 'server',
            'php_version' => $config['php_version'] ?? '8.3',
            'database_type' => $config['database_type'] ?? 'mysql-8.0',
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
        $this->request('post', "/servers/{$serverId}/restart");
        return true;
    }

    public function restartService(string $serverId, string $service): bool
    {
        $serviceMap = [
            'nginx' => 'nginx',
            'php' => 'php-fpm',
            'mysql' => 'mysql',
            'postgres' => 'postgresql',
            'redis' => 'redis',
        ];

        $serviceName = $serviceMap[$service] ?? $service;
        $this->request('post', "/servers/{$serverId}/restart-service", [
            'service' => $serviceName,
        ]);

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

            $allSites = collect();
            $servers = $this->listServers();

            foreach ($servers as $server) {
                try {
                    $response = $this->request('get', "/servers/{$server->id}/sites");
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
            'root_domain' => $config['domain'],
            'web_directory' => $config['web_directory'] ?? '/public',
            'project_type' => $config['project_type'] ?? 'laravel',
        ];

        $response = $this->request('post', "/servers/{$serverId}/sites", $payload);

        $this->clearCache("sites:{$serverId}");
        $this->clearCache('sites:all');

        return $this->mapSite($response['data'] ?? [], $serverId);
    }

    public function deleteSite(string $siteId): bool
    {
        $site = $this->getSite($siteId);
        $this->request('delete', "/servers/{$site->serverId}/sites/{$siteId}");

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->ensureCapability(Capability::PhpVersionSwitching);

        $site = $this->getSite($siteId);
        $this->request('patch', "/servers/{$site->serverId}/sites/{$siteId}", [
            'php_version' => $version->value,
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
                    'created_at' => $data['created_at'] ?? null,
                ]));
        });
    }

    public function createDatabase(string $serverId, string $name): Database
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $response = $this->request('post', "/servers/{$serverId}/databases", [
            'name' => $name,
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
            $response = $this->request('get', "/servers/{$serverId}/database-users");
            return collect($response['data'] ?? [])
                ->map(fn (array $data) => DatabaseUser::fromArray([
                    'id' => $data['id'],
                    'username' => $data['name'],
                    'server_id' => $serverId,
                    'databases' => $data['databases'] ?? [],
                ]));
        });
    }

    public function createDatabaseUser(string $serverId, string $username, string $password): DatabaseUser
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $response = $this->request('post', "/servers/{$serverId}/database-users", [
            'name' => $username,
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

        $this->request('delete', "/servers/{$serverId}/database-users/{$userId}");
        $this->clearCache("database_users:{$serverId}");

        return true;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);
        $response = $this->request('post', "/servers/{$site->serverId}/sites/{$siteId}/certificates");

        return new SslCertificate(
            id: (string) ($response['data']['id'] ?? uniqid('ssl_')),
            siteId: $siteId,
            status: SslStatus::Installing,
            provider: 'letsencrypt',
            autoRenewal: true,
            domains: [$site->domain],
        );
    }

    public function deploy(string $siteId): Deployment
    {
        $this->ensureCapability(Capability::GitDeployment);

        $site = $this->getSite($siteId);
        $response = $this->request('post', "/servers/{$site->serverId}/sites/{$siteId}/deploy");

        return new Deployment(
            id: (string) ($response['data']['id'] ?? uniqid('deploy_')),
            siteId: $siteId,
            status: DeploymentStatus::Running,
        );
    }

    public function listDeployments(string $siteId): Collection
    {
        $this->ensureCapability(Capability::GitDeployment);

        $site = $this->getSite($siteId);
        $response = $this->request('get', "/servers/{$site->serverId}/sites/{$siteId}/deployments");

        return collect($response['data'] ?? [])
            ->map(fn (array $data) => Deployment::fromArray([
                'id' => $data['id'],
                'site_id' => $siteId,
                'status' => $data['status'] ?? 'completed',
                'commit_hash' => $data['commit_hash'] ?? null,
                'started_at' => $data['started_at'] ?? null,
                'finished_at' => $data['finished_at'] ?? null,
            ]));
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
            phpVersion: PhpVersion::fromString($data['php_version'] ?? null),
            serverProvider: ServerProvider::fromString($data['provider'] ?? null),
            region: $data['region'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSite(array $data, string $serverId): Site
    {
        return new Site(
            id: (string) ($data['id'] ?? ''),
            serverId: $serverId,
            domain: $data['root_domain'] ?? $data['domain'] ?? '',
            status: SiteStatus::fromString($data['status'] ?? null),
            phpVersion: PhpVersion::fromString($data['php_version'] ?? null),
            sslEnabled: !empty($data['has_ssl']),
            sslStatus: !empty($data['has_ssl']) ? SslStatus::Active : SslStatus::None,
            documentRoot: $data['web_directory'] ?? null,
            projectType: $data['project_type'] ?? null,
            repository: $data['repository'] ?? null,
            repositoryBranch: $data['branch'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }
}
