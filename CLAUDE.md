# Laravel Hosting Management - Package Development Guidelines

## Package Overview

**Package Name:** `pstoute/laravel-hosting-management`
**Description:** A unified hosting provider abstraction layer for Laravel, providing a single API to manage servers, sites, databases, SSL certificates, and deployments across multiple hosting platforms (GridPane, Forge, Cloudways, Kinsta, WP Engine, Ploi, RunCloud, SpinupWP, cPanel).

**Target Audience:** Developers building hosting management dashboards, agency tools, DevOps automation, or any application requiring programmatic control over web hosting infrastructure.

## Source Reference

This package is being extracted from the Tomo-Agency codebase. Reference files are located at:
- **Source Codebase:** `/Users/paulstoute/Sites/Tomo-Agency`
- **Primary Services:** `app/Services/Hosting/`
- **Contract Interface:** `app/Contracts/HostingProviderInterface.php`
- **Related Models:** `app/Models/HostingAccount.php`, `app/Models/HostingServer.php`, `app/Models/HostingSite.php`

## Package Architecture

### Directory Structure

```
laravel-hosting-management/
├── src/
│   ├── Contracts/
│   │   ├── HostingProviderInterface.php
│   │   ├── ServerManagementInterface.php
│   │   ├── SiteManagementInterface.php
│   │   ├── DatabaseManagementInterface.php
│   │   ├── SslManagementInterface.php
│   │   └── DeploymentInterface.php
│   ├── Providers/
│   │   ├── AbstractHostingProvider.php
│   │   ├── GridPaneProvider.php
│   │   ├── ForgeProvider.php
│   │   ├── CloudwaysProvider.php
│   │   ├── KinstaProvider.php
│   │   ├── WPEngineProvider.php
│   │   ├── PloiProvider.php
│   │   ├── RunCloudProvider.php
│   │   ├── SpinupWPProvider.php
│   │   └── CPanelProvider.php
│   ├── Data/
│   │   ├── Server.php (DTO)
│   │   ├── Site.php (DTO)
│   │   ├── Database.php (DTO)
│   │   ├── DatabaseUser.php (DTO)
│   │   ├── SslCertificate.php (DTO)
│   │   ├── Deployment.php (DTO)
│   │   ├── ServerMetrics.php (DTO)
│   │   └── Backup.php (DTO)
│   ├── Enums/
│   │   ├── ServerStatus.php
│   │   ├── SiteStatus.php
│   │   ├── SslStatus.php
│   │   ├── PhpVersion.php
│   │   └── ServerProvider.php (AWS, DO, Vultr, etc.)
│   ├── Exceptions/
│   │   ├── HostingException.php
│   │   ├── ServerNotFoundException.php
│   │   ├── SiteNotFoundException.php
│   │   ├── ProvisioningException.php
│   │   ├── AuthenticationException.php
│   │   └── RateLimitException.php
│   ├── Events/
│   │   ├── ServerCreated.php
│   │   ├── ServerDeleted.php
│   │   ├── SiteCreated.php
│   │   ├── SiteDeleted.php
│   │   ├── SslCertificateInstalled.php
│   │   ├── DeploymentStarted.php
│   │   └── DeploymentCompleted.php
│   ├── Facades/
│   │   └── Hosting.php
│   ├── HostingManager.php
│   └── HostingServiceProvider.php
├── config/
│   └── hosting.php
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── Fakes/
│       └── FakeHostingProvider.php
├── docs/
│   ├── installation.md
│   ├── configuration.md
│   ├── usage.md
│   └── providers/
│       ├── gridpane.md
│       ├── forge.md
│       ├── cloudways.md
│       └── ...
├── composer.json
├── README.md
├── LICENSE
├── CHANGELOG.md
└── .github/
    └── workflows/
        └── tests.yml
```

### Core Interface Design

```php
<?php

namespace Pstoute\LaravelHosting\Contracts;

interface HostingProviderInterface
{
    // Server Management
    public function listServers(): Collection;
    public function getServer(string $serverId): Server;
    public function createServer(array $config): Server;
    public function deleteServer(string $serverId): bool;
    public function rebootServer(string $serverId): bool;
    public function getServerMetrics(string $serverId): ServerMetrics;

    // Site Management
    public function listSites(string $serverId): Collection;
    public function getSite(string $serverId, string $siteId): Site;
    public function createSite(string $serverId, array $config): Site;
    public function deleteSite(string $serverId, string $siteId): bool;
    public function updateSite(string $serverId, string $siteId, array $config): Site;

    // PHP Management
    public function getPhpVersion(string $serverId, string $siteId): PhpVersion;
    public function setPhpVersion(string $serverId, string $siteId, PhpVersion $version): bool;
    public function listAvailablePhpVersions(string $serverId): array;

    // Database Management
    public function listDatabases(string $serverId): Collection;
    public function createDatabase(string $serverId, string $name): Database;
    public function deleteDatabase(string $serverId, string $databaseId): bool;
    public function createDatabaseUser(string $serverId, string $username, string $password): DatabaseUser;
    public function deleteDatabaseUser(string $serverId, string $userId): bool;

    // SSL Management
    public function getSslCertificate(string $serverId, string $siteId): ?SslCertificate;
    public function installSslCertificate(string $serverId, string $siteId): SslCertificate;
    public function installCustomSsl(string $serverId, string $siteId, string $certificate, string $privateKey): SslCertificate;
    public function removeSslCertificate(string $serverId, string $siteId): bool;

    // Deployment
    public function deploy(string $serverId, string $siteId): Deployment;
    public function getDeploymentStatus(string $serverId, string $siteId, string $deploymentId): Deployment;
    public function listDeployments(string $serverId, string $siteId): Collection;
    public function rollback(string $serverId, string $siteId, string $deploymentId): Deployment;

    // Backups
    public function listBackups(string $serverId, string $siteId): Collection;
    public function createBackup(string $serverId, string $siteId): Backup;
    public function restoreBackup(string $serverId, string $siteId, string $backupId): bool;
    public function deleteBackup(string $serverId, string $siteId, string $backupId): bool;

    // Provider Info
    public function getProviderName(): string;
    public function supportsFeature(string $feature): bool;
}
```

### Facade Usage Examples

```php
use Pstoute\LaravelHosting\Facades\Hosting;
use Pstoute\LaravelHosting\Enums\PhpVersion;

// List all servers
$servers = Hosting::driver('forge')->listServers();

// Create a new site
$site = Hosting::driver('gridpane')->createSite('server-123', [
    'domain' => 'example.com',
    'php_version' => PhpVersion::PHP_83,
    'wordpress' => true,
]);

// Install SSL
$ssl = Hosting::driver('cloudways')
    ->installSslCertificate('server-123', 'site-456');

// Deploy
$deployment = Hosting::driver('ploi')->deploy('server-123', 'site-456');

// Use default driver
$servers = Hosting::listServers();
```

## Development Guidelines

### Code Style
- Follow PSR-12 coding standards
- Use PHP 8.2+ features (readonly properties, enums, named arguments)
- All public methods must have return types
- Use Data Transfer Objects (DTOs) instead of arrays for complex data
- Use Enums for status values and fixed options

### Provider Feature Matrix

Not all providers support all features. Track capabilities:

```php
public function supportsFeature(string $feature): bool
{
    return match ($feature) {
        'server_creation' => true,
        'wordpress_auto_install' => true,
        'staging_sites' => false,
        'git_deployment' => true,
        default => false,
    };
}
```

### Error Handling
- All provider-specific errors must be caught and converted to package exceptions
- Include original error message and code in exception context
- Implement retry logic with exponential backoff for rate limits
- Handle async operations (server provisioning) with polling

### Caching Strategy
- Cache server lists for 5 minutes
- Cache site lists for 5 minutes
- Cache SSL status for 1 hour
- Never cache deployment status (always real-time)
- All cache keys must be configurable

### Rate Limiting
- Implement per-provider rate limiting
- Track API calls and respect provider limits
- Queue bulk operations when approaching limits
- Some providers (Forge) have strict limits - document these

### Testing Requirements
- Unit tests for all DTOs and value objects
- Feature tests for each provider using HTTP mocks
- Provide `FakeHostingProvider` for application testing
- Minimum 80% code coverage
- Test async operations with mocked callbacks

### Documentation Requirements
- README with quick start guide
- Per-provider setup documentation (API keys, OAuth, etc.)
- Feature matrix showing what each provider supports
- API reference for all public methods
- Migration guide from direct API usage

## Configuration File Template

```php
<?php

return [
    'default' => env('HOSTING_PROVIDER', 'forge'),

    'cache' => [
        'enabled' => true,
        'prefix' => 'hosting',
        'ttl' => [
            'servers' => 300,       // 5 minutes
            'sites' => 300,         // 5 minutes
            'ssl' => 3600,          // 1 hour
            'databases' => 600,     // 10 minutes
        ],
    ],

    'rate_limits' => [
        'enabled' => true,
        'per_minute' => 60,
    ],

    'providers' => [
        'forge' => [
            'driver' => 'forge',
            'api_token' => env('FORGE_API_TOKEN'),
        ],

        'gridpane' => [
            'driver' => 'gridpane',
            'api_token' => env('GRIDPANE_API_TOKEN'),
        ],

        'cloudways' => [
            'driver' => 'cloudways',
            'email' => env('CLOUDWAYS_EMAIL'),
            'api_key' => env('CLOUDWAYS_API_KEY'),
        ],

        'kinsta' => [
            'driver' => 'kinsta',
            'api_key' => env('KINSTA_API_KEY'),
            'company_id' => env('KINSTA_COMPANY_ID'),
        ],

        'wpengine' => [
            'driver' => 'wpengine',
            'username' => env('WPENGINE_USERNAME'),
            'password' => env('WPENGINE_PASSWORD'),
        ],

        'ploi' => [
            'driver' => 'ploi',
            'api_token' => env('PLOI_API_TOKEN'),
        ],

        'runcloud' => [
            'driver' => 'runcloud',
            'api_key' => env('RUNCLOUD_API_KEY'),
            'api_secret' => env('RUNCLOUD_API_SECRET'),
        ],

        'spinupwp' => [
            'driver' => 'spinupwp',
            'api_token' => env('SPINUPWP_API_TOKEN'),
        ],

        'cpanel' => [
            'driver' => 'cpanel',
            'hostname' => env('CPANEL_HOSTNAME'),
            'username' => env('CPANEL_USERNAME'),
            'api_token' => env('CPANEL_API_TOKEN'),
        ],
    ],
];
```

## Extraction Checklist

When extracting from the source codebase:

- [ ] Extract `HostingProviderInterface.php` and adapt to new namespace
- [ ] Extract `AbstractHostingService.php` base class
- [ ] Extract each provider service (GridPane, Forge, Cloudways, Kinsta, WP Engine, Ploi, RunCloud, SpinupWP, cPanel)
- [ ] Create DTOs for Server, Site, Database, SSL, Deployment, Backup
- [ ] Create Enums for status values, PHP versions, server providers
- [ ] Remove all Tomo-Agency specific dependencies (models, settings, activity logging)
- [ ] Replace database models with DTOs
- [ ] Create events for server/site lifecycle
- [ ] Build FakeHostingProvider for testing
- [ ] Write comprehensive tests
- [ ] Create documentation
- [ ] Build feature matrix for each provider

## Dependencies

```json
{
    "require": {
        "php": "^8.2",
        "illuminate/support": "^10.0|^11.0|^12.0",
        "illuminate/contracts": "^10.0|^11.0|^12.0",
        "illuminate/http": "^10.0|^11.0|^12.0",
        "guzzlehttp/guzzle": "^7.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0|^10.0",
        "phpunit/phpunit": "^10.0|^11.0",
        "mockery/mockery": "^1.6"
    }
}
```

## Provider-Specific Notes

### Forge
- Official SDK exists (`laravel/forge-sdk`) - our abstraction adds unified interface
- Strict rate limits (60 requests/minute)
- Well-documented API

### GridPane
- No official SDK
- Good REST API
- Supports WordPress-specific features

### Cloudways
- No official SDK
- Requires email + API key
- Has application-level management

### Kinsta
- Newer API, well-designed
- Company/site hierarchy
- Good staging support

### WP Engine
- OAuth-based authentication
- WordPress-only
- Limited programmatic control

### Ploi
- Similar to Forge
- Growing feature set
- Good documentation

### RunCloud
- Key + Secret authentication
- Good server management
- WordPress optimization features

### SpinupWP
- WordPress-focused
- Clean API
- Limited to WP sites

### cPanel
- Token or password auth
- Legacy API (UAPI)
- Most complex integration

## Versioning

- Follow Semantic Versioning (SemVer)
- Major version bumps for breaking API changes
- Minor version bumps for new provider support
- Patch version bumps for bug fixes

## License

MIT License - This package will be open source.
