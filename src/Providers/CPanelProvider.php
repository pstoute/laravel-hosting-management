<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Pstoute\LaravelHosting\Data\Backup;
use Pstoute\LaravelHosting\Data\Database;
use Pstoute\LaravelHosting\Data\DatabaseUser;
use Pstoute\LaravelHosting\Data\Server;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Data\SslCertificate;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;
use Pstoute\LaravelHosting\Exceptions\ServerNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;

class CPanelProvider extends AbstractHostingProvider
{
    protected int $requestsPerMinute = 30;

    public function getName(): string
    {
        return 'cpanel';
    }

    public function getDisplayName(): string
    {
        return 'cPanel/WHM';
    }

    protected function getDefaultApiUrl(): string
    {
        // cPanel requires server-specific URL
        return $this->config['api_url'] ?? '';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiToken) && !empty($this->apiUrl) && !empty($this->config['username']);
    }

    protected function httpClient(): PendingRequest
    {
        $username = $this->config['username'] ?? 'root';

        return Http::withHeaders([
            'Authorization' => "whm {$username}:{$this->apiToken}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30)->withoutVerifying(); // Many cPanel servers have self-signed certs
    }

    public function getCapabilities(): array
    {
        return [
            Capability::ServerManagement,
            Capability::SiteProvisioning,
            Capability::SiteSuspension,
            Capability::SslInstallation,
            Capability::BackupCreation,
            Capability::BackupRestore,
            Capability::DatabaseManagement,
            Capability::PhpVersionSwitching,
            Capability::EmailManagement,
        ];
    }

    protected function whmApi(string $function, array $params = []): array
    {
        $url = rtrim($this->apiUrl, '/') . '/json-api/' . $function;
        $response = $this->httpClient()->get($url, array_merge(['api.version' => 1], $params));

        if (!$response->successful()) {
            throw new \Exception("WHM API Error: " . $response->body());
        }

        $data = $response->json();

        if (isset($data['metadata']['result']) && $data['metadata']['result'] !== 1) {
            throw new \Exception($data['metadata']['reason'] ?? 'Unknown WHM error');
        }

        return $data;
    }

    public function listServers(): Collection
    {
        // cPanel/WHM is typically one server
        // Return the connected server as a single server
        return $this->cached('servers', function () {
            try {
                $response = $this->whmApi('version');
                $version = $response['version'] ?? 'unknown';

                return collect([
                    new Server(
                        id: 'cpanel-' . md5($this->apiUrl),
                        name: parse_url($this->apiUrl, PHP_URL_HOST) ?? 'cPanel Server',
                        status: ServerStatus::Active,
                        ipAddress: gethostbyname(parse_url($this->apiUrl, PHP_URL_HOST) ?? ''),
                        metadata: ['cpanel_version' => $version],
                    ),
                ]);
            } catch (\Exception $e) {
                return collect();
            }
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

    public function listSites(?string $serverId = null): Collection
    {
        return $this->cached('sites', function () {
            $response = $this->whmApi('listaccts');
            $accounts = $response['data']['acct'] ?? [];

            $server = $this->listServers()->first();
            $serverId = $server?->id ?? 'cpanel-server';

            return collect($accounts)->map(fn (array $data) => $this->mapSite($data, $serverId));
        });
    }

    public function getSite(string $siteId): Site
    {
        $response = $this->whmApi('accountsummary', ['user' => $siteId]);
        $accounts = $response['data']['acct'] ?? [];

        if (empty($accounts)) {
            throw new SiteNotFoundException($siteId);
        }

        $server = $this->listServers()->first();
        return $this->mapSite($accounts[0], $server?->id ?? 'cpanel-server');
    }

    public function createSite(string $serverId, array $config): Site
    {
        $this->ensureCapability(Capability::SiteProvisioning);

        $username = $config['username'] ?? substr(preg_replace('/[^a-z]/', '', strtolower($config['domain'])) ?? '', 0, 8);

        $params = [
            'username' => $username,
            'domain' => $config['domain'],
            'password' => $config['password'] ?? bin2hex(random_bytes(8)),
            'plan' => $config['plan'] ?? 'default',
        ];

        if (isset($config['email'])) {
            $params['contactemail'] = $config['email'];
        }

        $response = $this->whmApi('createacct', $params);

        $this->clearCache('sites');

        return $this->getSite($username);
    }

    public function deleteSite(string $siteId): bool
    {
        $this->whmApi('removeacct', ['username' => $siteId]);
        $this->clearCache('sites');
        return true;
    }

    public function suspendSite(string $siteId): bool
    {
        $this->ensureCapability(Capability::SiteSuspension);

        $this->whmApi('suspendacct', ['username' => $siteId]);
        $this->clearCache('sites');
        return true;
    }

    public function unsuspendSite(string $siteId): bool
    {
        $this->ensureCapability(Capability::SiteSuspension);

        $this->whmApi('unsuspendacct', ['username' => $siteId]);
        $this->clearCache('sites');
        return true;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->ensureCapability(Capability::PhpVersionSwitching);

        $phpVersion = 'ea-php' . str_replace('.', '', $version->value);

        $this->whmApi('php_set_vhost_versions', [
            'vhost' => $this->getSite($siteId)->domain,
            'version' => $phpVersion,
        ]);

        $this->clearCache('sites');
        return true;
    }

    public function listDatabases(string $serverId): Collection
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        return $this->cached("databases:{$serverId}", function () use ($serverId) {
            $sites = $this->listSites($serverId);
            $databases = collect();

            foreach ($sites as $site) {
                try {
                    $response = $this->whmApi('list_databases_for_user', ['user' => $site->id]);
                    $dbs = $response['data']['payload'] ?? [];

                    foreach ($dbs as $db) {
                        $databases->push(Database::fromArray([
                            'id' => $db['db'] ?? $db['database'] ?? '',
                            'name' => $db['db'] ?? $db['database'] ?? '',
                            'server_id' => $serverId,
                            'site_id' => $site->id,
                        ]));
                    }
                } catch (\Exception) {
                    continue;
                }
            }

            return $databases;
        });
    }

    public function createDatabase(string $serverId, string $name): Database
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        // cPanel requires database to be prefixed with username
        // This is a simplified version - actual implementation would need to know the username
        $this->whmApi('create_database', ['name' => $name]);
        $this->clearCache("databases:{$serverId}");

        return Database::fromArray([
            'id' => $name,
            'name' => $name,
            'server_id' => $serverId,
        ]);
    }

    public function deleteDatabase(string $serverId, string $databaseId): bool
    {
        $this->ensureCapability(Capability::DatabaseManagement);

        $this->whmApi('delete_database', ['name' => $databaseId]);
        $this->clearCache("databases:{$serverId}");

        return true;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);

        $site = $this->getSite($siteId);

        // Request AutoSSL
        $this->whmApi('start_autossl_check_for_one_user', ['username' => $siteId]);

        return new SslCertificate(
            id: uniqid('ssl_'),
            siteId: $siteId,
            status: SslStatus::Installing,
            provider: 'autossl',
            autoRenewal: true,
            domains: [$site->domain],
        );
    }

    public function listBackups(string $siteId): Collection
    {
        $this->ensureCapability(Capability::BackupCreation);

        $response = $this->whmApi('backup_user_list', ['user' => $siteId]);
        $backups = $response['data']['backup'] ?? [];

        return collect($backups)->map(fn (array $data) => Backup::fromArray([
            'id' => $data['file'] ?? '',
            'site_id' => $siteId,
            'status' => 'completed',
            'created_at' => $data['date'] ?? null,
        ]));
    }

    public function createBackup(string $siteId, array $options = []): Backup
    {
        $this->ensureCapability(Capability::BackupCreation);

        $this->whmApi('backup_user_account', ['user' => $siteId]);

        return Backup::fromArray([
            'id' => uniqid('backup_'),
            'site_id' => $siteId,
            'status' => 'pending',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Mapping Methods
    |--------------------------------------------------------------------------
    */

    protected function mapSite(array $data, string $serverId): Site
    {
        $isSuspended = ($data['suspended'] ?? 0) === 1 || ($data['suspendtime'] ?? 0) > 0;

        return new Site(
            id: $data['user'] ?? '',
            serverId: $serverId,
            domain: $data['domain'] ?? '',
            status: $isSuspended ? SiteStatus::Suspended : SiteStatus::Active,
            sslEnabled: !empty($data['ssl']),
            sslStatus: !empty($data['ssl']) ? SslStatus::Active : SslStatus::None,
            systemUser: $data['user'] ?? null,
            documentRoot: $data['homedir'] ?? null,
            createdAt: isset($data['startdate']) ? new \DateTimeImmutable($data['startdate']) : null,
            metadata: $data,
        );
    }
}
