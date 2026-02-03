<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Facades;

use Illuminate\Support\Facades\Facade;
use Pstoute\LaravelHosting\Contracts\HostingProviderInterface;
use Pstoute\LaravelHosting\HostingManager;

/**
 * @method static HostingProviderInterface driver(string|null $name = null)
 * @method static string getDefaultDriver()
 * @method static array<string> getAvailableProviders()
 * @method static bool isConfigured(string $provider)
 * @method static array<string, HostingProviderInterface> getConfiguredProviders()
 * @method static HostingManager extend(string $driver, \Closure $callback)
 *
 * Provider Interface Methods (called on default driver):
 * @method static string getName()
 * @method static string getDisplayName()
 * @method static bool isConfigured()
 * @method static \Pstoute\LaravelHosting\Data\ConnectionResult testConnection()
 * @method static array<\Pstoute\LaravelHosting\Enums\Capability> getCapabilities()
 * @method static bool supportsCapability(\Pstoute\LaravelHosting\Enums\Capability $capability)
 * @method static \Illuminate\Support\Collection listServers()
 * @method static \Pstoute\LaravelHosting\Data\Server getServer(string $serverId)
 * @method static \Pstoute\LaravelHosting\Data\Server createServer(array $config)
 * @method static bool deleteServer(string $serverId)
 * @method static bool rebootServer(string $serverId)
 * @method static \Pstoute\LaravelHosting\Data\ServerMetrics getServerMetrics(string $serverId)
 * @method static bool restartService(string $serverId, string $service)
 * @method static array getProviderMetadata(?string $cloudProvider = null)
 * @method static \Illuminate\Support\Collection listSystemUsers(string $serverId)
 * @method static \Pstoute\LaravelHosting\Data\SystemUser getSystemUser(string $serverId, string $userId)
 * @method static \Pstoute\LaravelHosting\Data\SystemUser createSystemUser(string $serverId, array $config)
 * @method static bool deleteSystemUser(string $serverId, string $userId)
 * @method static \Illuminate\Support\Collection listSites(?string $serverId = null)
 * @method static \Pstoute\LaravelHosting\Data\Site getSite(string $siteId)
 * @method static \Pstoute\LaravelHosting\Data\Site createSite(string $serverId, array $config)
 * @method static bool deleteSite(string $siteId)
 * @method static bool suspendSite(string $siteId)
 * @method static bool unsuspendSite(string $siteId)
 * @method static array<\Pstoute\LaravelHosting\Enums\PhpVersion> getAvailablePhpVersions()
 * @method static \Pstoute\LaravelHosting\Enums\PhpVersion getPhpVersion(string $siteId)
 * @method static bool setPhpVersion(string $siteId, \Pstoute\LaravelHosting\Enums\PhpVersion $version)
 * @method static \Illuminate\Support\Collection listDatabases(string $serverId)
 * @method static \Pstoute\LaravelHosting\Data\Database createDatabase(string $serverId, string $name)
 * @method static bool deleteDatabase(string $serverId, string $databaseId)
 * @method static \Illuminate\Support\Collection listDatabaseUsers(string $serverId)
 * @method static \Pstoute\LaravelHosting\Data\DatabaseUser createDatabaseUser(string $serverId, string $username, string $password)
 * @method static bool deleteDatabaseUser(string $serverId, string $userId)
 * @method static \Pstoute\LaravelHosting\Data\SslCertificate|null getSslCertificate(string $siteId)
 * @method static \Pstoute\LaravelHosting\Data\SslCertificate installSslCertificate(string $siteId)
 * @method static \Pstoute\LaravelHosting\Data\SslCertificate installCustomSsl(string $siteId, string $certificate, string $privateKey)
 * @method static bool removeSslCertificate(string $siteId)
 * @method static \Pstoute\LaravelHosting\Data\Deployment deploy(string $siteId)
 * @method static \Pstoute\LaravelHosting\Data\Deployment getDeploymentStatus(string $siteId, string $deploymentId)
 * @method static \Illuminate\Support\Collection listDeployments(string $siteId)
 * @method static \Pstoute\LaravelHosting\Data\Deployment rollback(string $siteId, string $deploymentId)
 * @method static \Illuminate\Support\Collection listBackups(string $siteId)
 * @method static \Pstoute\LaravelHosting\Data\Backup createBackup(string $siteId, array $options = [])
 * @method static bool restoreBackup(string $siteId, string $backupId)
 * @method static bool deleteBackup(string $siteId, string $backupId)
 * @method static bool clearCache(string $siteId)
 *
 * @see \Pstoute\LaravelHosting\HostingManager
 */
class Hosting extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'hosting';
    }
}
