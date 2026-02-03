<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Testing;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Pstoute\LaravelHosting\Contracts\HostingProviderInterface;
use Pstoute\LaravelHosting\Data\Backup;
use Pstoute\LaravelHosting\Data\ConnectionResult;
use Pstoute\LaravelHosting\Data\Database;
use Pstoute\LaravelHosting\Data\DatabaseUser;
use Pstoute\LaravelHosting\Data\Deployment;
use Pstoute\LaravelHosting\Data\Server;
use Pstoute\LaravelHosting\Data\ServerMetrics;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Data\SslCertificate;
use Pstoute\LaravelHosting\Data\SystemUser;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\DeploymentStatus;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;
use Pstoute\LaravelHosting\Exceptions\HostingException;
use Pstoute\LaravelHosting\Exceptions\ServerNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;

class FakeHostingProvider implements HostingProviderInterface
{
    /** @var array<string, Server> */
    protected array $servers = [];

    /** @var array<string, Site> */
    protected array $sites = [];

    /** @var array<string, Database> */
    protected array $databases = [];

    /** @var array<string, DatabaseUser> */
    protected array $databaseUsers = [];

    /** @var array<string, SystemUser> */
    protected array $systemUsers = [];

    /** @var array<string, SslCertificate> */
    protected array $sslCertificates = [];

    /** @var array<string, Backup> */
    protected array $backups = [];

    /** @var array<string, Deployment> */
    protected array $deployments = [];

    /** @var array<array{method: string, args: array}> */
    protected array $recordedCalls = [];

    protected bool $shouldFail = false;
    protected ?string $failureMessage = null;

    /** @var array<Capability> */
    protected array $capabilities = [];

    protected bool $isConfigured = true;

    public function __construct()
    {
        $this->capabilities = Capability::cases();
    }

    /*
    |--------------------------------------------------------------------------
    | Setup Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Pre-populate with servers
     *
     * @param array<Server>|array<array<string, mixed>> $servers
     */
    public function withServers(array $servers): self
    {
        foreach ($servers as $server) {
            if (is_array($server)) {
                $server = Server::fromArray($server);
            }
            $this->servers[$server->id] = $server;
        }
        return $this;
    }

    /**
     * Pre-populate with sites
     *
     * @param array<Site>|array<array<string, mixed>> $sites
     */
    public function withSites(array $sites): self
    {
        foreach ($sites as $site) {
            if (is_array($site)) {
                $site = Site::fromArray($site);
            }
            $this->sites[$site->id] = $site;
        }
        return $this;
    }

    /**
     * Pre-populate with databases
     *
     * @param array<Database>|array<array<string, mixed>> $databases
     */
    public function withDatabases(array $databases): self
    {
        foreach ($databases as $database) {
            if (is_array($database)) {
                $database = Database::fromArray($database);
            }
            $this->databases[$database->id] = $database;
        }
        return $this;
    }

    /**
     * Configure to fail with specific message
     */
    public function shouldFailWith(string $message): self
    {
        $this->shouldFail = true;
        $this->failureMessage = $message;
        return $this;
    }

    /**
     * Configure capabilities
     *
     * @param array<Capability> $capabilities
     */
    public function withCapabilities(array $capabilities): self
    {
        $this->capabilities = $capabilities;
        return $this;
    }

    /**
     * Configure as not configured
     */
    public function notConfigured(): self
    {
        $this->isConfigured = false;
        return $this;
    }

    /**
     * Reset the fake provider state
     */
    public function reset(): self
    {
        $this->servers = [];
        $this->sites = [];
        $this->databases = [];
        $this->databaseUsers = [];
        $this->systemUsers = [];
        $this->sslCertificates = [];
        $this->backups = [];
        $this->deployments = [];
        $this->recordedCalls = [];
        $this->shouldFail = false;
        $this->failureMessage = null;
        $this->isConfigured = true;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Assertion Methods
    |--------------------------------------------------------------------------
    */

    public function assertServerCreated(?string $name = null): void
    {
        $calls = $this->getCallsTo('createServer');

        Assert::assertNotEmpty($calls, 'Expected createServer to be called, but it was not.');

        if ($name !== null) {
            $found = collect($calls)->contains(fn ($call) => ($call['args'][0]['name'] ?? null) === $name);
            Assert::assertTrue($found, "Expected server '{$name}' to be created, but it was not.");
        }
    }

    public function assertSiteCreated(?string $domain = null): void
    {
        $calls = $this->getCallsTo('createSite');

        Assert::assertNotEmpty($calls, 'Expected createSite to be called, but it was not.');

        if ($domain !== null) {
            $found = collect($calls)->contains(fn ($call) => ($call['args'][1]['domain'] ?? null) === $domain);
            Assert::assertTrue($found, "Expected site '{$domain}' to be created, but it was not.");
        }
    }

    public function assertSslInstalled(?string $siteId = null): void
    {
        $calls = $this->getCallsTo('installSslCertificate');

        Assert::assertNotEmpty($calls, 'Expected installSslCertificate to be called, but it was not.');

        if ($siteId !== null) {
            $found = collect($calls)->contains(fn ($call) => ($call['args'][0] ?? null) === $siteId);
            Assert::assertTrue($found, "Expected SSL to be installed on site '{$siteId}', but it was not.");
        }
    }

    public function assertDeployed(?string $siteId = null): void
    {
        $calls = $this->getCallsTo('deploy');

        Assert::assertNotEmpty($calls, 'Expected deploy to be called, but it was not.');

        if ($siteId !== null) {
            $found = collect($calls)->contains(fn ($call) => ($call['args'][0] ?? null) === $siteId);
            Assert::assertTrue($found, "Expected deployment on site '{$siteId}', but it was not.");
        }
    }

    public function assertBackupCreated(?string $siteId = null): void
    {
        $calls = $this->getCallsTo('createBackup');

        Assert::assertNotEmpty($calls, 'Expected createBackup to be called, but it was not.');

        if ($siteId !== null) {
            $found = collect($calls)->contains(fn ($call) => ($call['args'][0] ?? null) === $siteId);
            Assert::assertTrue($found, "Expected backup for site '{$siteId}', but it was not.");
        }
    }

    public function assertMethodCalled(string $method, int $times = null): void
    {
        $calls = $this->getCallsTo($method);

        Assert::assertNotEmpty($calls, "Expected {$method} to be called, but it was not.");

        if ($times !== null) {
            Assert::assertCount($times, $calls, "Expected {$method} to be called {$times} times, but it was called " . count($calls) . " times.");
        }
    }

    public function assertMethodNotCalled(string $method): void
    {
        $calls = $this->getCallsTo($method);

        Assert::assertEmpty($calls, "Expected {$method} not to be called, but it was called " . count($calls) . " times.");
    }

    /**
     * Get all recorded method calls
     *
     * @return array<array{method: string, args: array}>
     */
    public function getRecordedCalls(): array
    {
        return $this->recordedCalls;
    }

    /**
     * Get calls to a specific method
     *
     * @return array<array{method: string, args: array}>
     */
    public function getCallsTo(string $method): array
    {
        return array_filter($this->recordedCalls, fn ($call) => $call['method'] === $method);
    }

    /*
    |--------------------------------------------------------------------------
    | Interface Implementation
    |--------------------------------------------------------------------------
    */

    protected function recordCall(string $method, array $args = []): void
    {
        $this->recordedCalls[] = ['method' => $method, 'args' => $args];
    }

    protected function failIfConfigured(): void
    {
        if ($this->shouldFail) {
            throw new HostingException($this->failureMessage ?? 'Operation failed');
        }
    }

    public function getName(): string
    {
        return 'fake';
    }

    public function getDisplayName(): string
    {
        return 'Fake Provider';
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    public function testConnection(): ConnectionResult
    {
        $this->recordCall('testConnection');
        $this->failIfConfigured();

        return ConnectionResult::success('Connection successful', ['provider' => 'fake']);
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function supportsCapability(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function listServers(): Collection
    {
        $this->recordCall('listServers');
        $this->failIfConfigured();

        return collect(array_values($this->servers));
    }

    public function getServer(string $serverId): Server
    {
        $this->recordCall('getServer', [$serverId]);
        $this->failIfConfigured();

        if (!isset($this->servers[$serverId])) {
            throw new ServerNotFoundException($serverId);
        }

        return $this->servers[$serverId];
    }

    public function createServer(array $config): Server
    {
        $this->recordCall('createServer', [$config]);
        $this->failIfConfigured();

        $server = new Server(
            id: uniqid('server_'),
            name: $config['name'] ?? 'test-server',
            status: ServerStatus::Provisioning,
        );

        $this->servers[$server->id] = $server;

        return $server;
    }

    public function deleteServer(string $serverId): bool
    {
        $this->recordCall('deleteServer', [$serverId]);
        $this->failIfConfigured();

        unset($this->servers[$serverId]);

        return true;
    }

    public function rebootServer(string $serverId): bool
    {
        $this->recordCall('rebootServer', [$serverId]);
        $this->failIfConfigured();

        return true;
    }

    public function getServerMetrics(string $serverId): ServerMetrics
    {
        $this->recordCall('getServerMetrics', [$serverId]);
        $this->failIfConfigured();

        return new ServerMetrics(cpuUsage: 25.0, memoryUsage: 50.0, diskUsage: 30.0);
    }

    public function restartService(string $serverId, string $service): bool
    {
        $this->recordCall('restartService', [$serverId, $service]);
        $this->failIfConfigured();

        return true;
    }

    public function getProviderMetadata(?string $cloudProvider = null): array
    {
        $this->recordCall('getProviderMetadata', [$cloudProvider]);

        return ['regions' => ['nyc1', 'sfo1'], 'sizes' => ['1gb', '2gb']];
    }

    public function listSystemUsers(string $serverId): Collection
    {
        $this->recordCall('listSystemUsers', [$serverId]);
        $this->failIfConfigured();

        return collect(array_values(array_filter(
            $this->systemUsers,
            fn (SystemUser $u) => $u->serverId === $serverId
        )));
    }

    public function getSystemUser(string $serverId, string $userId): SystemUser
    {
        $this->recordCall('getSystemUser', [$serverId, $userId]);
        $this->failIfConfigured();

        if (!isset($this->systemUsers[$userId])) {
            throw new HostingException("System user not found: {$userId}");
        }

        return $this->systemUsers[$userId];
    }

    public function createSystemUser(string $serverId, array $config): SystemUser
    {
        $this->recordCall('createSystemUser', [$serverId, $config]);
        $this->failIfConfigured();

        $user = new SystemUser(
            id: uniqid('user_'),
            username: $config['username'] ?? 'testuser',
            serverId: $serverId,
        );

        $this->systemUsers[$user->id] = $user;

        return $user;
    }

    public function deleteSystemUser(string $serverId, string $userId): bool
    {
        $this->recordCall('deleteSystemUser', [$serverId, $userId]);
        $this->failIfConfigured();

        unset($this->systemUsers[$userId]);

        return true;
    }

    public function listSites(?string $serverId = null): Collection
    {
        $this->recordCall('listSites', [$serverId]);
        $this->failIfConfigured();

        $sites = collect(array_values($this->sites));

        if ($serverId !== null) {
            $sites = $sites->filter(fn (Site $s) => $s->serverId === $serverId);
        }

        return $sites;
    }

    public function getSite(string $siteId): Site
    {
        $this->recordCall('getSite', [$siteId]);
        $this->failIfConfigured();

        if (!isset($this->sites[$siteId])) {
            throw new SiteNotFoundException($siteId);
        }

        return $this->sites[$siteId];
    }

    public function createSite(string $serverId, array $config): Site
    {
        $this->recordCall('createSite', [$serverId, $config]);
        $this->failIfConfigured();

        $site = new Site(
            id: uniqid('site_'),
            serverId: $serverId,
            domain: $config['domain'] ?? 'example.com',
            status: SiteStatus::Installing,
        );

        $this->sites[$site->id] = $site;

        return $site;
    }

    public function deleteSite(string $siteId): bool
    {
        $this->recordCall('deleteSite', [$siteId]);
        $this->failIfConfigured();

        unset($this->sites[$siteId]);

        return true;
    }

    public function suspendSite(string $siteId): bool
    {
        $this->recordCall('suspendSite', [$siteId]);
        $this->failIfConfigured();

        return true;
    }

    public function unsuspendSite(string $siteId): bool
    {
        $this->recordCall('unsuspendSite', [$siteId]);
        $this->failIfConfigured();

        return true;
    }

    public function getAvailablePhpVersions(): array
    {
        return PhpVersion::cases();
    }

    public function getPhpVersion(string $siteId): PhpVersion
    {
        $this->recordCall('getPhpVersion', [$siteId]);

        return $this->sites[$siteId]?->phpVersion ?? PhpVersion::PHP_83;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->recordCall('setPhpVersion', [$siteId, $version]);
        $this->failIfConfigured();

        return true;
    }

    public function listDatabases(string $serverId): Collection
    {
        $this->recordCall('listDatabases', [$serverId]);
        $this->failIfConfigured();

        return collect(array_values(array_filter(
            $this->databases,
            fn (Database $d) => $d->serverId === $serverId
        )));
    }

    public function createDatabase(string $serverId, string $name): Database
    {
        $this->recordCall('createDatabase', [$serverId, $name]);
        $this->failIfConfigured();

        $database = new Database(id: uniqid('db_'), name: $name, serverId: $serverId);
        $this->databases[$database->id] = $database;

        return $database;
    }

    public function deleteDatabase(string $serverId, string $databaseId): bool
    {
        $this->recordCall('deleteDatabase', [$serverId, $databaseId]);
        $this->failIfConfigured();

        unset($this->databases[$databaseId]);

        return true;
    }

    public function listDatabaseUsers(string $serverId): Collection
    {
        $this->recordCall('listDatabaseUsers', [$serverId]);
        $this->failIfConfigured();

        return collect(array_values(array_filter(
            $this->databaseUsers,
            fn (DatabaseUser $u) => $u->serverId === $serverId
        )));
    }

    public function createDatabaseUser(string $serverId, string $username, string $password): DatabaseUser
    {
        $this->recordCall('createDatabaseUser', [$serverId, $username, $password]);
        $this->failIfConfigured();

        $user = new DatabaseUser(id: uniqid('dbuser_'), username: $username, serverId: $serverId);
        $this->databaseUsers[$user->id] = $user;

        return $user;
    }

    public function deleteDatabaseUser(string $serverId, string $userId): bool
    {
        $this->recordCall('deleteDatabaseUser', [$serverId, $userId]);
        $this->failIfConfigured();

        unset($this->databaseUsers[$userId]);

        return true;
    }

    public function getSslCertificate(string $siteId): ?SslCertificate
    {
        $this->recordCall('getSslCertificate', [$siteId]);

        return $this->sslCertificates[$siteId] ?? null;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->recordCall('installSslCertificate', [$siteId]);
        $this->failIfConfigured();

        $cert = new SslCertificate(
            id: uniqid('ssl_'),
            siteId: $siteId,
            status: SslStatus::Installing,
            provider: 'letsencrypt',
        );

        $this->sslCertificates[$siteId] = $cert;

        return $cert;
    }

    public function installCustomSsl(string $siteId, string $certificate, string $privateKey): SslCertificate
    {
        $this->recordCall('installCustomSsl', [$siteId, $certificate, $privateKey]);
        $this->failIfConfigured();

        $cert = new SslCertificate(
            id: uniqid('ssl_'),
            siteId: $siteId,
            status: SslStatus::Active,
            provider: 'custom',
        );

        $this->sslCertificates[$siteId] = $cert;

        return $cert;
    }

    public function removeSslCertificate(string $siteId): bool
    {
        $this->recordCall('removeSslCertificate', [$siteId]);
        $this->failIfConfigured();

        unset($this->sslCertificates[$siteId]);

        return true;
    }

    public function deploy(string $siteId): Deployment
    {
        $this->recordCall('deploy', [$siteId]);
        $this->failIfConfigured();

        $deployment = new Deployment(
            id: uniqid('deploy_'),
            siteId: $siteId,
            status: DeploymentStatus::Running,
        );

        $this->deployments[$deployment->id] = $deployment;

        return $deployment;
    }

    public function getDeploymentStatus(string $siteId, string $deploymentId): Deployment
    {
        $this->recordCall('getDeploymentStatus', [$siteId, $deploymentId]);

        return $this->deployments[$deploymentId] ?? new Deployment(
            id: $deploymentId,
            siteId: $siteId,
            status: DeploymentStatus::Succeeded,
        );
    }

    public function listDeployments(string $siteId): Collection
    {
        $this->recordCall('listDeployments', [$siteId]);

        return collect(array_values(array_filter(
            $this->deployments,
            fn (Deployment $d) => $d->siteId === $siteId
        )));
    }

    public function rollback(string $siteId, string $deploymentId): Deployment
    {
        $this->recordCall('rollback', [$siteId, $deploymentId]);
        $this->failIfConfigured();

        return new Deployment(
            id: uniqid('deploy_'),
            siteId: $siteId,
            status: DeploymentStatus::Running,
        );
    }

    public function listBackups(string $siteId): Collection
    {
        $this->recordCall('listBackups', [$siteId]);

        return collect(array_values(array_filter(
            $this->backups,
            fn (Backup $b) => $b->siteId === $siteId
        )));
    }

    public function createBackup(string $siteId, array $options = []): Backup
    {
        $this->recordCall('createBackup', [$siteId, $options]);
        $this->failIfConfigured();

        $backup = Backup::fromArray([
            'id' => uniqid('backup_'),
            'site_id' => $siteId,
            'status' => 'pending',
        ]);

        $this->backups[$backup->id] = $backup;

        return $backup;
    }

    public function restoreBackup(string $siteId, string $backupId): bool
    {
        $this->recordCall('restoreBackup', [$siteId, $backupId]);
        $this->failIfConfigured();

        return true;
    }

    public function deleteBackup(string $siteId, string $backupId): bool
    {
        $this->recordCall('deleteBackup', [$siteId, $backupId]);
        $this->failIfConfigured();

        unset($this->backups[$backupId]);

        return true;
    }

    public function clearCache(string $siteId): bool
    {
        $this->recordCall('clearCache', [$siteId]);
        $this->failIfConfigured();

        return true;
    }
}
