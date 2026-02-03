<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Support\Collection;
use Pstoute\LaravelHosting\Data\Backup;
use Pstoute\LaravelHosting\Data\ConnectionResult;
use Pstoute\LaravelHosting\Data\Database;
use Pstoute\LaravelHosting\Data\DatabaseUser;
use Pstoute\LaravelHosting\Data\Deployment;
use Pstoute\LaravelHosting\Data\Server;
use Pstoute\LaravelHosting\Data\ServerMetrics;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Data\SslCertificate;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\DeploymentStatus;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\ServerProvider;
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;
use Pstoute\LaravelHosting\Events\ServerCreated;
use Pstoute\LaravelHosting\Events\SiteCreated;
use Pstoute\LaravelHosting\Events\SslCertificateInstalled;
use Pstoute\LaravelHosting\Exceptions\ServerNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SslException;

class ForgeProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 30;

    public function getName(): string
    {
        return 'forge';
    }

    public function getDisplayName(): string
    {
        return 'Laravel Forge';
    }

    protected function getDefaultApiUrl(): string
    {
        return 'https://forge.laravel.com/api/v1';
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
            Capability::SshAccess,
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
                ['server_count' => count($response['servers'] ?? [])],
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
            $servers = $response['servers'] ?? [];

            return collect($servers)->map(fn (array $data) => $this->mapServer($data));
        });
    }

    public function getServer(string $serverId): Server
    {
        try {
            $response = $this->request('get', "/servers/{$serverId}");
            return $this->mapServer($response['server'] ?? []);
        } catch (\Exception $e) {
            throw new ServerNotFoundException($serverId, "Server not found: {$e->getMessage()}");
        }
    }

    public function createServer(array $config): Server
    {
        $this->ensureCapability(Capability::ServerProvisioning);

        $payload = [
            'name' => $config['name'],
            'provider' => $config['provider'] ?? 'ocean2',
            'region' => $config['region'] ?? 'nyc3',
            'size' => $config['size'] ?? '1gb',
            'php_version' => $this->formatPhpVersion($config['php_version'] ?? '8.3'),
            'database' => $config['database_type'] ?? 'mysql8',
        ];

        if (isset($config['ubuntu_version'])) {
            $payload['ubuntu_version'] = $config['ubuntu_version'];
        }

        $response = $this->request('post', '/servers', $payload);
        $server = $this->mapServer($response['server'] ?? []);

        $this->clearCache('servers');
        event(new ServerCreated($this->getName(), $server));

        return $server;
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

    public function restartService(string $serverId, string $service): bool
    {
        $validServices = ['nginx', 'php', 'mysql', 'postgres'];
        if (!in_array($service, $validServices, true)) {
            return false;
        }

        $this->request('post', "/servers/{$serverId}/{$service}/restart");
        return true;
    }

    public function listSites(?string $serverId = null): Collection
    {
        $cacheKey = $serverId ? "sites:{$serverId}" : 'sites:all';

        return $this->cached($cacheKey, function () use ($serverId) {
            if ($serverId) {
                $response = $this->request('get', "/servers/{$serverId}/sites");
                return collect($response['sites'] ?? [])
                    ->map(fn (array $data) => $this->mapSite($data, $serverId));
            }

            // List all sites from all servers
            $allSites = collect();
            $servers = $this->listServers();

            foreach ($servers as $server) {
                $response = $this->request('get', "/servers/{$server->id}/sites");
                $sites = collect($response['sites'] ?? [])
                    ->map(fn (array $data) => $this->mapSite($data, $server->id));
                $allSites = $allSites->merge($sites);
            }

            return $allSites;
        });
    }

    public function getSite(string $siteId): Site
    {
        // Forge requires server_id, try to find from cached sites
        $allSites = $this->listSites();
        $site = $allSites->first(fn (Site $s) => $s->id === $siteId);

        if ($site) {
            $response = $this->request('get', "/servers/{$site->serverId}/sites/{$siteId}");
            return $this->mapSite($response['site'] ?? [], $site->serverId);
        }

        throw new SiteNotFoundException($siteId);
    }

    public function createSite(string $serverId, array $config): Site
    {
        $payload = [
            'domain' => $config['domain'],
            'project_type' => $config['project_type'] ?? 'php',
            'php_version' => $this->formatPhpVersion($config['php_version'] ?? '8.3'),
            'directory' => $config['directory'] ?? '/public',
        ];

        if (isset($config['aliases'])) {
            $payload['aliases'] = $config['aliases'];
        }

        if (isset($config['isolated']) && $config['isolated']) {
            $payload['isolated'] = true;
            $payload['username'] = $config['username'] ?? str_replace('.', '', $config['domain']);
        }

        $response = $this->request('post', "/servers/{$serverId}/sites", $payload);
        $site = $this->mapSite($response['site'] ?? [], $serverId);

        $this->clearCache("sites:{$serverId}");
        $this->clearCache('sites:all');
        event(new SiteCreated($this->getName(), $site));

        return $site;
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
        $site = $this->getSite($siteId);
        $this->request('put', "/servers/{$site->serverId}/sites/{$siteId}/php", [
            'version' => $this->formatPhpVersion($version->value),
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
            return collect($response['databases'] ?? [])
                ->map(fn (array $data) => $this->mapDatabase($data, $serverId));
        });
    }

    public function createDatabase(string $serverId, string $name): Database
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $response = $this->request('post', "/servers/{$serverId}/databases", [
            'name' => $name,
        ]);

        $this->clearCache("databases:{$serverId}");

        return $this->mapDatabase($response['database'] ?? [], $serverId);
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
            return collect($response['users'] ?? [])
                ->map(fn (array $data) => $this->mapDatabaseUser($data, $serverId));
        });
    }

    public function createDatabaseUser(string $serverId, string $username, string $password): DatabaseUser
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $response = $this->request('post', "/servers/{$serverId}/database-users", [
            'name' => $username,
            'password' => $password,
            'databases' => [],
        ]);

        $this->clearCache("database_users:{$serverId}");

        return $this->mapDatabaseUser($response['user'] ?? [], $serverId);
    }

    public function deleteDatabaseUser(string $serverId, string $userId): bool
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $this->request('delete', "/servers/{$serverId}/database-users/{$userId}");
        $this->clearCache("database_users:{$serverId}");

        return true;
    }

    public function getSslCertificate(string $siteId): ?SslCertificate
    {
        $site = $this->getSite($siteId);

        try {
            $response = $this->request('get', "/servers/{$site->serverId}/sites/{$siteId}/certificates");
            $certificates = $response['certificates'] ?? [];

            if (empty($certificates)) {
                return null;
            }

            // Get the first active certificate
            $activeCert = collect($certificates)->first(fn ($cert) => $cert['active'] ?? false);
            if (!$activeCert) {
                $activeCert = $certificates[0];
            }

            return $this->mapSslCertificate($activeCert, $siteId);
        } catch (\Exception) {
            return null;
        }
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);

        $response = $this->request('post', "/servers/{$site->serverId}/sites/{$siteId}/certificates/letsencrypt", [
            'domains' => [$site->domain],
        ]);

        $certificate = $this->mapSslCertificate($response['certificate'] ?? [], $siteId);

        event(new SslCertificateInstalled($this->getName(), $site->serverId, $siteId, $certificate));

        return $certificate;
    }

    public function installCustomSsl(string $siteId, string $certificate, string $privateKey): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);

        $response = $this->request('post', "/servers/{$site->serverId}/sites/{$siteId}/certificates", [
            'type' => 'existing',
            'certificate' => $certificate,
            'private_key' => $privateKey,
        ]);

        $cert = $this->mapSslCertificate($response['certificate'] ?? [], $siteId);

        event(new SslCertificateInstalled($this->getName(), $site->serverId, $siteId, $cert));

        return $cert;
    }

    public function removeSslCertificate(string $siteId): bool
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);
        $cert = $this->getSslCertificate($siteId);

        if (!$cert) {
            return true;
        }

        $this->request('delete', "/servers/{$site->serverId}/sites/{$siteId}/certificates/{$cert->id}");

        return true;
    }

    public function deploy(string $siteId): Deployment
    {
        $this->ensureCapability(Capability::GitDeployment);

        $site = $this->getSite($siteId);
        $response = $this->request('post', "/servers/{$site->serverId}/sites/{$siteId}/deployment/deploy");

        return new Deployment(
            id: (string) ($response['id'] ?? uniqid('deploy_')),
            siteId: $siteId,
            status: DeploymentStatus::Pending,
        );
    }

    public function getDeploymentStatus(string $siteId, string $deploymentId): Deployment
    {
        $this->ensureCapability(Capability::GitDeployment);

        $site = $this->getSite($siteId);
        $response = $this->request('get', "/servers/{$site->serverId}/sites/{$siteId}/deployment-history");

        $deployment = collect($response['deployments'] ?? [])
            ->first(fn ($d) => (string) $d['id'] === $deploymentId);

        if (!$deployment) {
            return new Deployment(
                id: $deploymentId,
                siteId: $siteId,
                status: DeploymentStatus::Unknown,
            );
        }

        return $this->mapDeployment($deployment, $siteId);
    }

    public function listDeployments(string $siteId): Collection
    {
        $this->ensureCapability(Capability::GitDeployment);

        $site = $this->getSite($siteId);
        $response = $this->request('get', "/servers/{$site->serverId}/sites/{$siteId}/deployment-history");

        return collect($response['deployments'] ?? [])
            ->map(fn (array $data) => $this->mapDeployment($data, $siteId));
    }

    public function getProviderMetadata(?string $cloudProvider = null): array
    {
        try {
            $response = $this->request('get', '/credentials');
            return $response;
        } catch (\Exception) {
            return [];
        }
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
            status: $this->mapServerStatus($data['is_ready'] ?? false),
            ipAddress: $data['ip_address'] ?? null,
            privateIpAddress: $data['private_ip_address'] ?? null,
            phpVersion: PhpVersion::fromString($data['php_version'] ?? null),
            serverProvider: ServerProvider::fromString($data['provider'] ?? null),
            region: $data['region'] ?? null,
            size: $data['size'] ?? null,
            ubuntuVersion: $data['ubuntu_version'] ?? null,
            databaseType: $data['database_type'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapServerStatus(bool $isReady): ServerStatus
    {
        return $isReady ? ServerStatus::Active : ServerStatus::Provisioning;
    }

    protected function mapSite(array $data, string $serverId): Site
    {
        return new Site(
            id: (string) ($data['id'] ?? ''),
            serverId: $serverId,
            domain: $data['name'] ?? $data['domain'] ?? '',
            status: $this->mapSiteStatus($data['status'] ?? null),
            phpVersion: PhpVersion::fromString($data['php_version'] ?? null),
            sslEnabled: !empty($data['is_secured']),
            sslStatus: !empty($data['is_secured']) ? SslStatus::Active : SslStatus::None,
            documentRoot: $data['directory'] ?? null,
            systemUser: $data['username'] ?? null,
            projectType: $data['project_type'] ?? null,
            isWordPress: ($data['project_type'] ?? '') === 'wordpress',
            repository: $data['repository'] ?? null,
            repositoryBranch: $data['repository_branch'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSiteStatus(?string $status): SiteStatus
    {
        return match ($status) {
            'installed' => SiteStatus::Active,
            'installing' => SiteStatus::Installing,
            default => SiteStatus::fromString($status),
        };
    }

    protected function mapDatabase(array $data, string $serverId): Database
    {
        return new Database(
            id: (string) ($data['id'] ?? ''),
            name: $data['name'] ?? '',
            serverId: $serverId,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapDatabaseUser(array $data, string $serverId): DatabaseUser
    {
        return new DatabaseUser(
            id: (string) ($data['id'] ?? ''),
            username: $data['name'] ?? '',
            serverId: $serverId,
            databases: $data['databases'] ?? [],
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSslCertificate(array $data, string $siteId): SslCertificate
    {
        return new SslCertificate(
            id: (string) ($data['id'] ?? ''),
            siteId: $siteId,
            status: $data['active'] ?? false ? SslStatus::Active : SslStatus::Pending,
            provider: $data['type'] ?? 'letsencrypt',
            autoRenewal: ($data['type'] ?? '') === 'letsencrypt',
            expiresAt: isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
            domains: $data['domain'] ? [$data['domain']] : [],
            metadata: $data,
        );
    }

    protected function mapDeployment(array $data, string $siteId): Deployment
    {
        return new Deployment(
            id: (string) ($data['id'] ?? ''),
            siteId: $siteId,
            status: DeploymentStatus::fromString($data['status'] ?? null),
            commitHash: $data['commit_hash'] ?? null,
            commitMessage: $data['commit_message'] ?? null,
            commitAuthor: $data['commit_author'] ?? null,
            startedAt: isset($data['started_at']) ? new \DateTimeImmutable($data['started_at']) : null,
            finishedAt: isset($data['ended_at']) ? new \DateTimeImmutable($data['ended_at']) : null,
            metadata: $data,
        );
    }

    protected function formatPhpVersion(string $version): string
    {
        // Forge uses 'php82' format
        return 'php' . str_replace('.', '', $version);
    }
}
