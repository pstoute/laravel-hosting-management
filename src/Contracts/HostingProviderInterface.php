<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Contracts;

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
use Pstoute\LaravelHosting\Data\SystemUser;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\PhpVersion;

interface HostingProviderInterface
{
    /*
    |--------------------------------------------------------------------------
    | Identity & Configuration
    |--------------------------------------------------------------------------
    */

    /**
     * Get the internal provider name (e.g., 'forge', 'gridpane')
     */
    public function getName(): string;

    /**
     * Get the display name for UI (e.g., 'Laravel Forge', 'GridPane')
     */
    public function getDisplayName(): string;

    /**
     * Check if the provider is properly configured with credentials
     */
    public function isConfigured(): bool;

    /**
     * Test the API connection and return result
     */
    public function testConnection(): ConnectionResult;

    /**
     * Get all capabilities this provider supports
     *
     * @return array<Capability>
     */
    public function getCapabilities(): array;

    /**
     * Check if the provider supports a specific capability
     */
    public function supportsCapability(Capability $capability): bool;

    /*
    |--------------------------------------------------------------------------
    | Server Operations
    |--------------------------------------------------------------------------
    */

    /**
     * List all servers from this provider
     *
     * @return Collection<int, Server>
     */
    public function listServers(): Collection;

    /**
     * Get a specific server by its provider ID
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     */
    public function getServer(string $serverId): Server;

    /**
     * Create/provision a new server
     *
     * @param array{
     *     name: string,
     *     provider?: string,
     *     region?: string,
     *     size?: string,
     *     ip_address?: string,
     *     ubuntu_version?: string,
     *     database_type?: string,
     *     php_version?: string,
     *     timezone?: string,
     *     post_provision_script?: string
     * } $config
     * @throws \Pstoute\LaravelHosting\Exceptions\ProvisioningException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function createServer(array $config): Server;

    /**
     * Delete a server
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     */
    public function deleteServer(string $serverId): bool;

    /**
     * Reboot a server
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     */
    public function rebootServer(string $serverId): bool;

    /**
     * Get server metrics (CPU, memory, disk usage)
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function getServerMetrics(string $serverId): ServerMetrics;

    /**
     * Restart a service on a server
     *
     * @param string $service Service name (nginx, mysql, php, redis, etc.)
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     */
    public function restartService(string $serverId, string $service): bool;

    /**
     * Get available regions and sizes from cloud providers
     *
     * @param string|null $cloudProvider e.g., 'digitalocean', 'vultr', 'linode', 'hetzner'
     * @return array{regions?: array, sizes?: array}
     */
    public function getProviderMetadata(?string $cloudProvider = null): array;

    /*
    |--------------------------------------------------------------------------
    | System User Operations
    |--------------------------------------------------------------------------
    */

    /**
     * List all system users on a server
     *
     * @return Collection<int, SystemUser>
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function listSystemUsers(string $serverId): Collection;

    /**
     * Get a specific system user
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function getSystemUser(string $serverId, string $userId): SystemUser;

    /**
     * Create a new system user on a server
     *
     * @param array{
     *     username: string,
     *     password?: string,
     *     ssh_public_key?: string,
     *     isolated?: bool,
     *     groups?: array<string>
     * } $config
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\ProvisioningException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function createSystemUser(string $serverId, array $config): SystemUser;

    /**
     * Delete a system user from a server
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function deleteSystemUser(string $serverId, string $userId): bool;

    /*
    |--------------------------------------------------------------------------
    | Site Operations
    |--------------------------------------------------------------------------
    */

    /**
     * List all sites, optionally filtered by server
     *
     * @return Collection<int, Site>
     */
    public function listSites(?string $serverId = null): Collection;

    /**
     * Get a specific site by its provider ID
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     */
    public function getSite(string $siteId): Site;

    /**
     * Create a new site
     *
     * @param array{
     *     domain: string,
     *     site_user?: string,
     *     php_version?: string,
     *     project_type?: string,
     *     install_wordpress?: bool,
     *     admin_email?: string,
     *     admin_user?: string,
     *     admin_password?: string,
     *     install_ssl?: bool,
     *     database?: array{name?: string, username?: string, password?: string},
     *     git?: array{repo?: string, branch?: string, deploy_script?: string}
     * } $config
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\ProvisioningException
     */
    public function createSite(string $serverId, array $config): Site;

    /**
     * Delete a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     */
    public function deleteSite(string $siteId): bool;

    /**
     * Suspend a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function suspendSite(string $siteId): bool;

    /**
     * Unsuspend (reactivate) a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function unsuspendSite(string $siteId): bool;

    /*
    |--------------------------------------------------------------------------
    | PHP Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get available PHP versions on this provider
     *
     * @return array<PhpVersion>
     */
    public function getAvailablePhpVersions(): array;

    /**
     * Get the current PHP version for a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     */
    public function getPhpVersion(string $siteId): PhpVersion;

    /**
     * Change PHP version for a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function setPhpVersion(string $siteId, PhpVersion $version): bool;

    /*
    |--------------------------------------------------------------------------
    | Database Operations
    |--------------------------------------------------------------------------
    */

    /**
     * List all databases on a server
     *
     * @return Collection<int, Database>
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function listDatabases(string $serverId): Collection;

    /**
     * Create a new database on a server
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\ProvisioningException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function createDatabase(string $serverId, string $name): Database;

    /**
     * Delete a database from a server
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function deleteDatabase(string $serverId, string $databaseId): bool;

    /**
     * List all database users on a server
     *
     * @return Collection<int, DatabaseUser>
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function listDatabaseUsers(string $serverId): Collection;

    /**
     * Create a new database user on a server
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\ProvisioningException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function createDatabaseUser(string $serverId, string $username, string $password): DatabaseUser;

    /**
     * Delete a database user from a server
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\ServerNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function deleteDatabaseUser(string $serverId, string $userId): bool;

    /*
    |--------------------------------------------------------------------------
    | SSL Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get SSL certificate information for a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     */
    public function getSslCertificate(string $siteId): ?SslCertificate;

    /**
     * Install SSL certificate (Let's Encrypt) for a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\SslException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function installSslCertificate(string $siteId): SslCertificate;

    /**
     * Install a custom SSL certificate for a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\SslException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function installCustomSsl(string $siteId, string $certificate, string $privateKey): SslCertificate;

    /**
     * Remove SSL certificate from a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function removeSslCertificate(string $siteId): bool;

    /*
    |--------------------------------------------------------------------------
    | Deployment Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Trigger a deployment for a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function deploy(string $siteId): Deployment;

    /**
     * Get the status of a deployment
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     */
    public function getDeploymentStatus(string $siteId, string $deploymentId): Deployment;

    /**
     * List recent deployments for a site
     *
     * @return Collection<int, Deployment>
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function listDeployments(string $siteId): Collection;

    /**
     * Rollback to a previous deployment
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function rollback(string $siteId, string $deploymentId): Deployment;

    /*
    |--------------------------------------------------------------------------
    | Backup Operations
    |--------------------------------------------------------------------------
    */

    /**
     * List all backups for a site
     *
     * @return Collection<int, Backup>
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function listBackups(string $siteId): Collection;

    /**
     * Create a backup for a site
     *
     * @param array{type?: string, description?: string} $options
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function createBackup(string $siteId, array $options = []): Backup;

    /**
     * Restore a site from a backup
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function restoreBackup(string $siteId, string $backupId): bool;

    /**
     * Delete a backup
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function deleteBackup(string $siteId, string $backupId): bool;

    /*
    |--------------------------------------------------------------------------
    | Cache Management
    |--------------------------------------------------------------------------
    */

    /**
     * Clear cache for a site
     *
     * @throws \Pstoute\LaravelHosting\Exceptions\SiteNotFoundException
     * @throws \Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException
     */
    public function clearCache(string $siteId): bool;
}
