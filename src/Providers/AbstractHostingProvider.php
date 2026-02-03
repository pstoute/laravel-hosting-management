<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Exceptions\AuthenticationException;
use Pstoute\LaravelHosting\Exceptions\HostingException;
use Pstoute\LaravelHosting\Exceptions\RateLimitException;
use Pstoute\LaravelHosting\Exceptions\UnsupportedOperationException;

abstract class AbstractHostingProvider implements HostingProviderInterface
{
    protected string $apiUrl = '';
    protected ?string $apiToken = null;
    protected int $requestsPerMinute = 60;
    protected int $defaultCacheTtl = 300; // 5 minutes
    protected string $cachePrefix = 'hosting:';

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Create a new provider instance
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->apiToken = $config['api_token'] ?? null;
        $this->apiUrl = $config['api_url'] ?? $this->getDefaultApiUrl();

        $this->initializeFromConfig();
    }

    /**
     * Get the default API URL for this provider
     */
    abstract protected function getDefaultApiUrl(): string;

    /**
     * Initialize any additional configuration
     */
    protected function initializeFromConfig(): void
    {
        // Override in child classes if needed
    }

    /**
     * Get all capabilities this provider supports
     *
     * @return array<Capability>
     */
    abstract public function getCapabilities(): array;

    /**
     * Check if the provider supports a specific capability
     */
    public function supportsCapability(Capability $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    /**
     * Check if the provider is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiToken) && !empty($this->apiUrl);
    }

    /**
     * Create an HTTP client with proper headers
     */
    protected function httpClient(): PendingRequest
    {
        return Http::withHeaders($this->getDefaultHeaders())
            ->timeout(30)
            ->retry(3, 100, function (\Exception $exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            });
    }

    /**
     * Get default HTTP headers for requests
     *
     * @return array<string, string>
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Make a rate-limited API request
     *
     * @param string $method HTTP method (get, post, put, patch, delete)
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $data Request data
     * @return array<string, mixed>
     * @throws HostingException
     * @throws RateLimitException
     * @throws AuthenticationException
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $rateLimiterKey = 'hosting:' . $this->getName() . ':api';

        if (RateLimiter::tooManyAttempts($rateLimiterKey, $this->requestsPerMinute)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            throw RateLimitException::withRetryAfter($seconds, $this->getDisplayName());
        }

        RateLimiter::hit($rateLimiterKey, 60);

        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');
        $startTime = microtime(true);

        try {
            $response = match (strtolower($method)) {
                'get' => $this->httpClient()->get($url, $data),
                'post' => $this->httpClient()->post($url, $data),
                'put' => $this->httpClient()->put($url, $data),
                'patch' => $this->httpClient()->patch($url, $data),
                'delete' => $this->httpClient()->delete($url, $data),
                default => throw new HostingException("Unsupported HTTP method: {$method}"),
            };

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->status() === 401) {
                throw AuthenticationException::invalidToken($this->getDisplayName());
            }

            if ($response->status() === 403) {
                throw AuthenticationException::insufficientPermissions($this->getDisplayName());
            }

            if ($response->status() === 429) {
                $retryAfter = $response->header('Retry-After');
                throw RateLimitException::withRetryAfter(
                    $retryAfter ? (int) $retryAfter : 60,
                    $this->getDisplayName()
                );
            }

            if (!$response->successful()) {
                Log::error("{$this->getName()} API Error", [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'duration_ms' => $duration,
                ]);

                throw HostingException::withContext(
                    "API request failed: " . ($response->json('message') ?? $response->body()),
                    [
                        'status_code' => $response->status(),
                        'endpoint' => $endpoint,
                        'duration_ms' => $duration,
                    ]
                );
            }

            return $response->json() ?? [];

        } catch (HostingException|RateLimitException|AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("{$this->getName()} API Exception", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw HostingException::withContext(
                "API request failed: " . $e->getMessage(),
                ['endpoint' => $endpoint],
                0,
                $e
            );
        }
    }

    /**
     * Get cached data or fetch fresh
     *
     * @template T
     * @param string $key Cache key
     * @param \Closure(): T $callback
     * @param int|null $ttl Cache TTL in seconds
     * @return T
     */
    protected function cached(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        if (!$this->isCacheEnabled()) {
            return $callback();
        }

        $cacheKey = $this->getCacheKey($key);

        return Cache::remember($cacheKey, $ttl ?? $this->defaultCacheTtl, $callback);
    }

    /**
     * Clear cache for a specific key
     */
    protected function clearCache(string $key): void
    {
        Cache::forget($this->getCacheKey($key));
    }

    /**
     * Clear all cache for this provider
     */
    protected function clearAllCache(): void
    {
        $keys = [
            'servers',
            'sites',
            'databases',
            'system_users',
        ];

        foreach ($keys as $key) {
            $this->clearCache($key);
        }
    }

    /**
     * Get the full cache key
     */
    protected function getCacheKey(string $key): string
    {
        return $this->cachePrefix . $this->getName() . ':' . $key;
    }

    /**
     * Check if caching is enabled
     */
    protected function isCacheEnabled(): bool
    {
        return $this->config['cache_enabled'] ?? true;
    }

    /**
     * Ensure a capability is supported before performing an operation
     *
     * @throws UnsupportedOperationException
     */
    protected function ensureCapability(Capability $capability): void
    {
        if (!$this->supportsCapability($capability)) {
            throw UnsupportedOperationException::capability($capability, $this->getDisplayName());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Default Implementations - Override in child classes
    |--------------------------------------------------------------------------
    */

    public function testConnection(): ConnectionResult
    {
        if (!$this->isConfigured()) {
            return ConnectionResult::failure(
                'Provider not configured. Missing API credentials.',
                null,
                ['provider' => $this->getName()]
            );
        }

        $startTime = microtime(true);

        try {
            // Default implementation: try to list servers
            $this->request('get', $this->getTestConnectionEndpoint());
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            return ConnectionResult::success(
                'Connection successful',
                ['provider' => $this->getName()],
                $latencyMs
            );
        } catch (\Exception $e) {
            return ConnectionResult::failure(
                $e->getMessage(),
                $e->getCode() ?: null,
                ['provider' => $this->getName()]
            );
        }
    }

    /**
     * Get the endpoint to use for connection testing
     */
    protected function getTestConnectionEndpoint(): string
    {
        return 'servers';
    }

    public function listServers(): Collection
    {
        return collect();
    }

    public function getServer(string $serverId): Server
    {
        throw UnsupportedOperationException::notImplemented('getServer', $this->getDisplayName());
    }

    public function createServer(array $config): Server
    {
        $this->ensureCapability(Capability::ServerProvisioning);
        throw UnsupportedOperationException::notImplemented('createServer', $this->getDisplayName());
    }

    public function deleteServer(string $serverId): bool
    {
        $this->ensureCapability(Capability::ServerManagement);
        throw UnsupportedOperationException::notImplemented('deleteServer', $this->getDisplayName());
    }

    public function rebootServer(string $serverId): bool
    {
        $this->ensureCapability(Capability::ServerManagement);
        throw UnsupportedOperationException::notImplemented('rebootServer', $this->getDisplayName());
    }

    public function getServerMetrics(string $serverId): ServerMetrics
    {
        $this->ensureCapability(Capability::ResourceMonitoring);
        throw UnsupportedOperationException::notImplemented('getServerMetrics', $this->getDisplayName());
    }

    public function restartService(string $serverId, string $service): bool
    {
        $this->ensureCapability(Capability::ServerManagement);
        throw UnsupportedOperationException::notImplemented('restartService', $this->getDisplayName());
    }

    public function getProviderMetadata(?string $cloudProvider = null): array
    {
        return [];
    }

    public function listSystemUsers(string $serverId): Collection
    {
        $this->ensureCapability(Capability::SystemUserManagement);
        return collect();
    }

    public function getSystemUser(string $serverId, string $userId): SystemUser
    {
        $this->ensureCapability(Capability::SystemUserManagement);
        throw UnsupportedOperationException::notImplemented('getSystemUser', $this->getDisplayName());
    }

    public function createSystemUser(string $serverId, array $config): SystemUser
    {
        $this->ensureCapability(Capability::SystemUserManagement);
        throw UnsupportedOperationException::notImplemented('createSystemUser', $this->getDisplayName());
    }

    public function deleteSystemUser(string $serverId, string $userId): bool
    {
        $this->ensureCapability(Capability::SystemUserManagement);
        throw UnsupportedOperationException::notImplemented('deleteSystemUser', $this->getDisplayName());
    }

    public function listSites(?string $serverId = null): Collection
    {
        return collect();
    }

    public function getSite(string $siteId): Site
    {
        throw UnsupportedOperationException::notImplemented('getSite', $this->getDisplayName());
    }

    public function createSite(string $serverId, array $config): Site
    {
        $this->ensureCapability(Capability::SiteProvisioning);
        throw UnsupportedOperationException::notImplemented('createSite', $this->getDisplayName());
    }

    public function deleteSite(string $siteId): bool
    {
        throw UnsupportedOperationException::notImplemented('deleteSite', $this->getDisplayName());
    }

    public function suspendSite(string $siteId): bool
    {
        $this->ensureCapability(Capability::SiteSuspension);
        throw UnsupportedOperationException::notImplemented('suspendSite', $this->getDisplayName());
    }

    public function unsuspendSite(string $siteId): bool
    {
        $this->ensureCapability(Capability::SiteSuspension);
        throw UnsupportedOperationException::notImplemented('unsuspendSite', $this->getDisplayName());
    }

    public function getAvailablePhpVersions(): array
    {
        return [
            PhpVersion::PHP_74,
            PhpVersion::PHP_80,
            PhpVersion::PHP_81,
            PhpVersion::PHP_82,
            PhpVersion::PHP_83,
        ];
    }

    public function getPhpVersion(string $siteId): PhpVersion
    {
        $site = $this->getSite($siteId);
        return $site->phpVersion ?? PhpVersion::PHP_83;
    }

    public function setPhpVersion(string $siteId, PhpVersion $version): bool
    {
        $this->ensureCapability(Capability::PhpVersionSwitching);
        throw UnsupportedOperationException::notImplemented('setPhpVersion', $this->getDisplayName());
    }

    public function listDatabases(string $serverId): Collection
    {
        $this->ensureCapability(Capability::DatabaseManagement);
        return collect();
    }

    public function createDatabase(string $serverId, string $name): Database
    {
        $this->ensureCapability(Capability::DatabaseManagement);
        throw UnsupportedOperationException::notImplemented('createDatabase', $this->getDisplayName());
    }

    public function deleteDatabase(string $serverId, string $databaseId): bool
    {
        $this->ensureCapability(Capability::DatabaseManagement);
        throw UnsupportedOperationException::notImplemented('deleteDatabase', $this->getDisplayName());
    }

    public function listDatabaseUsers(string $serverId): Collection
    {
        $this->ensureCapability(Capability::DatabaseManagement);
        return collect();
    }

    public function createDatabaseUser(string $serverId, string $username, string $password): DatabaseUser
    {
        $this->ensureCapability(Capability::DatabaseManagement);
        throw UnsupportedOperationException::notImplemented('createDatabaseUser', $this->getDisplayName());
    }

    public function deleteDatabaseUser(string $serverId, string $userId): bool
    {
        $this->ensureCapability(Capability::DatabaseManagement);
        throw UnsupportedOperationException::notImplemented('deleteDatabaseUser', $this->getDisplayName());
    }

    public function getSslCertificate(string $siteId): ?SslCertificate
    {
        return null;
    }

    public function installSslCertificate(string $siteId): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);
        throw UnsupportedOperationException::notImplemented('installSslCertificate', $this->getDisplayName());
    }

    public function installCustomSsl(string $siteId, string $certificate, string $privateKey): SslCertificate
    {
        $this->ensureCapability(Capability::SslInstallation);
        throw UnsupportedOperationException::notImplemented('installCustomSsl', $this->getDisplayName());
    }

    public function removeSslCertificate(string $siteId): bool
    {
        $this->ensureCapability(Capability::SslInstallation);
        throw UnsupportedOperationException::notImplemented('removeSslCertificate', $this->getDisplayName());
    }

    public function deploy(string $siteId): Deployment
    {
        $this->ensureCapability(Capability::GitDeployment);
        throw UnsupportedOperationException::notImplemented('deploy', $this->getDisplayName());
    }

    public function getDeploymentStatus(string $siteId, string $deploymentId): Deployment
    {
        $this->ensureCapability(Capability::GitDeployment);
        throw UnsupportedOperationException::notImplemented('getDeploymentStatus', $this->getDisplayName());
    }

    public function listDeployments(string $siteId): Collection
    {
        $this->ensureCapability(Capability::GitDeployment);
        return collect();
    }

    public function rollback(string $siteId, string $deploymentId): Deployment
    {
        $this->ensureCapability(Capability::GitDeployment);
        throw UnsupportedOperationException::notImplemented('rollback', $this->getDisplayName());
    }

    public function listBackups(string $siteId): Collection
    {
        $this->ensureCapability(Capability::BackupCreation);
        return collect();
    }

    public function createBackup(string $siteId, array $options = []): Backup
    {
        $this->ensureCapability(Capability::BackupCreation);
        throw UnsupportedOperationException::notImplemented('createBackup', $this->getDisplayName());
    }

    public function restoreBackup(string $siteId, string $backupId): bool
    {
        $this->ensureCapability(Capability::BackupRestore);
        throw UnsupportedOperationException::notImplemented('restoreBackup', $this->getDisplayName());
    }

    public function deleteBackup(string $siteId, string $backupId): bool
    {
        $this->ensureCapability(Capability::BackupCreation);
        throw UnsupportedOperationException::notImplemented('deleteBackup', $this->getDisplayName());
    }

    public function clearCache(string $siteId): bool
    {
        $this->ensureCapability(Capability::CacheClearing);
        throw UnsupportedOperationException::notImplemented('clearCache', $this->getDisplayName());
    }
}
