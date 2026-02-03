<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Pstoute\LaravelHosting\Data\Backup;
use Pstoute\LaravelHosting\Data\ConnectionResult;
use Pstoute\LaravelHosting\Data\Server;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Data\SslCertificate;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\ServerProvider;
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;
use Pstoute\LaravelHosting\Exceptions\AuthenticationException;
use Pstoute\LaravelHosting\Exceptions\ServerNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;

class CloudwaysProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 30;
    protected ?string $accessToken = null;
    protected ?int $tokenExpiresAt = null;

    public function getName(): string
    {
        return 'cloudways';
    }

    public function getDisplayName(): string
    {
        return 'Cloudways';
    }

    protected function getDefaultApiUrl(): string
    {
        return 'https://api.cloudways.com/api/v1';
    }

    protected function initializeFromConfig(): void
    {
        // Cloudways uses email + API key for OAuth
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['api_token']) && !empty($this->config['email']);
    }

    public function getCapabilities(): array
    {
        return [
            Capability::ServerManagement,
            Capability::ServerProvisioning,
            Capability::SiteProvisioning,
            Capability::SslInstallation,
            Capability::SslAutoRenewal,
            Capability::BackupCreation,
            Capability::BackupRestore,
            Capability::PhpVersionSwitching,
            Capability::CacheClearing,
            Capability::GitDeployment,
            Capability::StagingSites,
        ];
    }

    /**
     * Get OAuth access token
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $response = Http::post($this->apiUrl . '/oauth/access_token', [
            'email' => $this->config['email'],
            'api_key' => $this->config['api_token'],
        ]);

        if (!$response->successful()) {
            throw AuthenticationException::invalidToken($this->getDisplayName());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];
        $this->tokenExpiresAt = time() + ($data['expires_in'] ?? 3600) - 60;

        return $this->accessToken;
    }

    protected function httpClient(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    public function testConnection(): ConnectionResult
    {
        if (!$this->isConfigured()) {
            return ConnectionResult::failure('Email and API key not configured');
        }

        $startTime = microtime(true);

        try {
            $this->getAccessToken();
            $response = $this->request('get', '/server');
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

    protected function getTestConnectionEndpoint(): string
    {
        return 'server';
    }

    public function listServers(): Collection
    {
        return $this->cached('servers', function () {
            $response = $this->request('get', '/server');
            $servers = $response['servers'] ?? [];

            return collect($servers)->map(fn (array $data) => $this->mapServer($data));
        });
    }

    public function getServer(string $serverId): Server
    {
        $servers = $this->listServers();
        $server = $servers->first(fn (Server $s) => $s->id === $serverId);

        if (!$server) {
            throw new ServerNotFoundException($serverId);
        }

        return $server;
    }

    public function createServer(array $config): Server
    {
        $this->ensureCapability(Capability::ServerProvisioning);

        $payload = [
            'cloud' => $config['provider'] ?? 'do',
            'region' => $config['region'] ?? 'nyc3',
            'instance_type' => $config['size'] ?? '1GB',
            'application' => $config['application'] ?? 'wordpress',
            'app_version' => $config['app_version'] ?? 'latest',
            'server_label' => $config['name'],
        ];

        $response = $this->request('post', '/server', $payload);
        $this->clearCache('servers');

        return $this->mapServer($response['server'] ?? []);
    }

    public function deleteServer(string $serverId): bool
    {
        $this->request('delete', "/server/{$serverId}");
        $this->clearCache('servers');
        return true;
    }

    public function listSites(?string $serverId = null): Collection
    {
        $cacheKey = $serverId ? "sites:{$serverId}" : 'sites:all';

        return $this->cached($cacheKey, function () use ($serverId) {
            if ($serverId) {
                $response = $this->request('get', "/server/{$serverId}");
                $apps = $response['server']['apps'] ?? [];
                return collect($apps)
                    ->map(fn (array $data) => $this->mapSite($data, $serverId));
            }

            $allSites = collect();
            $servers = $this->listServers();

            foreach ($servers as $server) {
                try {
                    $response = $this->request('get', "/server/{$server->id}");
                    $apps = $response['server']['apps'] ?? [];
                    $sites = collect($apps)
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
            'server_id' => $serverId,
            'application' => $config['application'] ?? 'wordpress',
            'app_version' => $config['app_version'] ?? 'latest',
            'app_label' => $config['domain'],
        ];

        $response = $this->request('post', '/app', $payload);

        $this->clearCache("sites:{$serverId}");
        $this->clearCache('sites:all');

        return $this->mapSite($response['app'] ?? [], $serverId);
    }

    public function deleteSite(string $siteId): bool
    {
        $site = $this->getSite($siteId);
        $this->request('delete', "/app/{$siteId}");

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->ensureCapability(Capability::PhpVersionSwitching);

        $site = $this->getSite($siteId);
        $this->request('post', "/app/{$siteId}/fpm_setting", [
            'server_id' => $site->serverId,
            'version' => $version->value,
        ]);

        $this->clearCache("sites:{$site->serverId}");
        $this->clearCache('sites:all');

        return true;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);
        $response = $this->request('post', "/security/lets_encrypt_install", [
            'server_id' => $site->serverId,
            'app_id' => $siteId,
            'ssl_email' => $this->config['email'] ?? '',
            'wild_card' => false,
        ]);

        return new SslCertificate(
            id: uniqid('ssl_'),
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

        $site = $this->getSite($siteId);
        $response = $this->request('get', "/app/{$siteId}/backup", [
            'server_id' => $site->serverId,
        ]);

        return collect($response['backups'] ?? [])
            ->map(fn (array $data) => Backup::fromArray([
                'id' => $data['id'] ?? '',
                'site_id' => $siteId,
                'status' => $data['status'] ?? 'completed',
                'created_at' => $data['created_at'] ?? null,
            ]));
    }

    public function createBackup(string $siteId, array $options = []): Backup
    {
        $this->ensureCapability(Capability::BackupCreation);

        $site = $this->getSite($siteId);
        $this->request('post', "/app/{$siteId}/backup", [
            'server_id' => $site->serverId,
        ]);

        return Backup::fromArray([
            'id' => uniqid('backup_'),
            'site_id' => $siteId,
            'status' => 'pending',
        ]);
    }

    public function clearCache(string $siteId): bool
    {
        $this->ensureCapability(Capability::CacheClearing);

        $site = $this->getSite($siteId);
        $this->request('post', "/app/{$siteId}/caches", [
            'server_id' => $site->serverId,
            'action' => 'purge',
        ]);

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
            name: $data['label'] ?? '',
            status: ServerStatus::fromString($data['status'] ?? null),
            ipAddress: $data['public_ip'] ?? null,
            serverProvider: ServerProvider::fromString($data['cloud'] ?? null),
            region: $data['region'] ?? null,
            size: $data['instance_type'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }

    protected function mapSite(array $data, string $serverId): Site
    {
        return new Site(
            id: (string) ($data['id'] ?? ''),
            serverId: $serverId,
            domain: $data['cname'] ?? $data['app_fqdn'] ?? '',
            status: SiteStatus::fromString($data['status'] ?? null),
            phpVersion: PhpVersion::fromString($data['app_version'] ?? null),
            sslEnabled: !empty($data['ssl_installed']),
            sslStatus: !empty($data['ssl_installed']) ? SslStatus::Active : SslStatus::None,
            projectType: $data['application'] ?? null,
            isWordPress: str_contains($data['application'] ?? '', 'wordpress'),
            isStaging: !empty($data['is_staging']),
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            metadata: $data,
        );
    }
}
