# Laravel Hosting Management

A unified hosting provider abstraction layer for Laravel. Manage servers, sites, databases, SSL certificates, and deployments across multiple hosting platforms with a single, consistent API.

## Supported Providers

| Provider | Server Management | Site Management | SSL | Deployments | Backups |
|----------|:-----------------:|:---------------:|:---:|:-----------:|:-------:|
| Laravel Forge | Yes | Yes | Yes | Yes | Yes |
| GridPane | Yes | Yes | Yes | Yes | Yes |
| Cloudways | Yes | Yes | Yes | Yes | Yes |
| Kinsta | Yes | Yes | Yes | Yes | Yes |
| WP Engine | Limited | Yes | Yes | Yes | Yes |
| Ploi | Yes | Yes | Yes | Yes | Yes |
| RunCloud | Yes | Yes | Yes | Yes | Yes |
| SpinupWP | Yes | Yes | Yes | Yes | Yes |
| cPanel/WHM | Limited | Yes | Yes | No | Yes |

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- Guzzle HTTP 7.x

## Installation

Install via Composer:

```bash
composer require pstoute/laravel-hosting-management
```

The service provider will be automatically registered via Laravel's package discovery.

### Publish Configuration

```bash
php artisan vendor:publish --provider="Pstoute\LaravelHosting\HostingServiceProvider"
```

## Configuration

Add your provider credentials to your `.env` file:

```env
# Default provider
HOSTING_PROVIDER=forge

# Laravel Forge
FORGE_API_TOKEN=your-forge-api-token

# GridPane
GRIDPANE_API_TOKEN=your-gridpane-api-token

# Cloudways
CLOUDWAYS_EMAIL=your-email@example.com
CLOUDWAYS_API_KEY=your-cloudways-api-key

# Kinsta
KINSTA_API_KEY=your-kinsta-api-key
KINSTA_COMPANY_ID=your-company-id

# WP Engine
WPENGINE_USERNAME=your-username
WPENGINE_PASSWORD=your-password
WPENGINE_ACCOUNT_ID=your-account-id

# Ploi
PLOI_API_TOKEN=your-ploi-api-token

# RunCloud
RUNCLOUD_API_KEY=your-api-key
RUNCLOUD_API_SECRET=your-api-secret

# SpinupWP
SPINUPWP_API_TOKEN=your-spinupwp-api-token

# cPanel/WHM
CPANEL_API_URL=https://server.example.com:2087
CPANEL_API_TOKEN=your-cpanel-api-token
CPANEL_USERNAME=root
```

## Quick Start

### Using the Facade

```php
use Pstoute\LaravelHosting\Facades\Hosting;

// Use the default provider
$servers = Hosting::listServers();

// Use a specific provider
$servers = Hosting::driver('forge')->listServers();
$sites = Hosting::driver('gridpane')->listSites();
```

### Server Management

```php
use Pstoute\LaravelHosting\Facades\Hosting;
use Pstoute\LaravelHosting\Enums\ServerStatus;

// List all servers
$servers = Hosting::listServers();

// Filter operational servers
$operational = $servers->filter(fn ($s) => $s->status === ServerStatus::Active);

// Get a specific server
$server = Hosting::getServer('server-id');

// Create a new server
$server = Hosting::createServer([
    'name' => 'my-new-server',
    'provider' => 'digitalocean',
    'region' => 'nyc1',
    'size' => 's-1vcpu-1gb',
    'php_version' => '8.3',
]);

// Reboot a server
Hosting::rebootServer('server-id');

// Delete a server
Hosting::deleteServer('server-id');

// Get server metrics
$metrics = Hosting::getServerMetrics('server-id');
echo "CPU: {$metrics->cpuUsage}%";
echo "Memory: {$metrics->memoryUsage}%";
echo "Disk: {$metrics->diskUsage}%";
```

### Site Management

```php
use Pstoute\LaravelHosting\Facades\Hosting;
use Pstoute\LaravelHosting\Enums\PhpVersion;

// List all sites
$sites = Hosting::listSites();

// List sites for a specific server
$sites = Hosting::listSites('server-id');

// Get a specific site
$site = Hosting::getSite('site-id');

// Create a new site
$site = Hosting::createSite('server-id', [
    'domain' => 'example.com',
    'php_version' => '8.3',
    'wordpress' => true,
]);

// Update PHP version
Hosting::setPhpVersion('site-id', PhpVersion::PHP_84);

// Suspend/unsuspend a site
Hosting::suspendSite('site-id');
Hosting::unsuspendSite('site-id');

// Delete a site
Hosting::deleteSite('site-id');
```

### SSL Certificate Management

```php
use Pstoute\LaravelHosting\Facades\Hosting;

// Get SSL certificate status
$ssl = Hosting::getSslCertificate('site-id');

if ($ssl && $ssl->expiresSoon(30)) {
    echo "SSL expires in less than 30 days!";
}

// Install Let's Encrypt SSL
$ssl = Hosting::installSslCertificate('site-id');

// Install custom SSL certificate
$ssl = Hosting::installCustomSsl(
    'site-id',
    $certificateContent,
    $privateKeyContent
);

// Remove SSL certificate
Hosting::removeSslCertificate('site-id');
```

### Database Management

```php
use Pstoute\LaravelHosting\Facades\Hosting;

// List databases on a server
$databases = Hosting::listDatabases('server-id');

// Create a database
$database = Hosting::createDatabase('server-id', 'my_database');

// Create a database user
$user = Hosting::createDatabaseUser('server-id', 'db_user', 'secure_password');

// Delete database
Hosting::deleteDatabase('server-id', 'database-id');
```

### Deployments

```php
use Pstoute\LaravelHosting\Facades\Hosting;

// Trigger a deployment
$deployment = Hosting::deploy('site-id');

// Check deployment status
$deployment = Hosting::getDeploymentStatus('site-id', $deployment->id);

if ($deployment->isSuccessful()) {
    echo "Deployment completed in {$deployment->humanReadableDuration()}";
}

// List deployment history
$deployments = Hosting::listDeployments('site-id');

// Rollback to a previous deployment
$rollback = Hosting::rollback('site-id', 'deployment-id');
```

### Backups

```php
use Pstoute\LaravelHosting\Facades\Hosting;

// List backups
$backups = Hosting::listBackups('site-id');

// Create a backup
$backup = Hosting::createBackup('site-id', [
    'type' => 'full', // or 'database', 'files'
]);

// Restore from backup
Hosting::restoreBackup('site-id', 'backup-id');

// Delete a backup
Hosting::deleteBackup('site-id', 'backup-id');
```

### Connection Testing

```php
use Pstoute\LaravelHosting\Facades\Hosting;

// Test connection to the provider
$result = Hosting::testConnection();

if ($result->success) {
    echo "Connected successfully!";
    echo "Latency: {$result->latencyMs}ms";
} else {
    echo "Connection failed: {$result->message}";
}
```

### Checking Provider Capabilities

```php
use Pstoute\LaravelHosting\Facades\Hosting;
use Pstoute\LaravelHosting\Enums\Capability;

// Check if provider supports a capability
if (Hosting::supportsCapability(Capability::GitDeployment)) {
    Hosting::deploy('site-id');
}

// Get all capabilities
$capabilities = Hosting::getCapabilities();
```

## Data Transfer Objects (DTOs)

All data returned from provider methods uses strongly-typed DTOs:

### Server

```php
$server->id;            // string
$server->name;          // string
$server->ipAddress;     // ?string
$server->status;        // ServerStatus enum
$server->phpVersion;    // ?PhpVersion enum
$server->isOperational(); // bool
$server->toArray();     // array
```

### Site

```php
$site->id;              // string
$site->serverId;        // string
$site->domain;          // string
$site->status;          // SiteStatus enum
$site->sslEnabled;      // bool
$site->isWordPress;     // bool
$site->hasValidSsl();   // bool
```

### SslCertificate

```php
$ssl->status;           // SslStatus enum
$ssl->expiresAt;        // ?DateTimeImmutable
$ssl->isValid();        // bool
$ssl->isExpired();      // bool
$ssl->expiresSoon(30);  // bool (days threshold)
```

### Deployment

```php
$deployment->status;              // DeploymentStatus enum
$deployment->isSuccessful();      // bool
$deployment->isComplete();        // bool
$deployment->durationSeconds;     // ?int
$deployment->humanReadableDuration(); // string
```

### ServerMetrics

```php
$metrics->cpuUsage;              // float
$metrics->memoryUsage;           // float
$metrics->diskUsage;             // float
$metrics->isCpuCritical(90);     // bool
$metrics->isMemoryCritical(90);  // bool
$metrics->humanReadableUptime(); // string
```

## Enums

### ServerStatus

```php
ServerStatus::Provisioning
ServerStatus::Active
ServerStatus::Inactive
ServerStatus::Rebooting
ServerStatus::Failed
ServerStatus::Deleting
ServerStatus::Unknown
```

### SiteStatus

```php
SiteStatus::Installing
SiteStatus::Active
SiteStatus::Suspended
SiteStatus::Maintenance
SiteStatus::Failed
SiteStatus::Deleting
SiteStatus::Unknown
```

### PhpVersion

```php
PhpVersion::PHP_74
PhpVersion::PHP_80
PhpVersion::PHP_81
PhpVersion::PHP_82
PhpVersion::PHP_83
PhpVersion::PHP_84

// Utilities
PhpVersion::fromString('8.3');     // PhpVersion::PHP_83
PhpVersion::latest();               // PhpVersion::PHP_84
PhpVersion::recommended();          // PhpVersion::PHP_83
PhpVersion::PHP_83->isSupported(); // bool
```

### Capability

```php
Capability::ServerManagement
Capability::SiteManagement
Capability::DatabaseManagement
Capability::SslInstallation
Capability::GitDeployment
Capability::AutoDeployment
Capability::Backups
// ... and more
```

## Events

The package dispatches events for key operations:

```php
// Server events
Pstoute\LaravelHosting\Events\ServerCreated
Pstoute\LaravelHosting\Events\ServerProvisioned
Pstoute\LaravelHosting\Events\ServerDeleted
Pstoute\LaravelHosting\Events\ServerRebooted

// Site events
Pstoute\LaravelHosting\Events\SiteCreated
Pstoute\LaravelHosting\Events\SiteDeleted
Pstoute\LaravelHosting\Events\SiteSuspended
Pstoute\LaravelHosting\Events\SiteUnsuspended

// SSL events
Pstoute\LaravelHosting\Events\SslCertificateInstalled
Pstoute\LaravelHosting\Events\SslCertificateExpiring

// Deployment events
Pstoute\LaravelHosting\Events\DeploymentStarted
Pstoute\LaravelHosting\Events\DeploymentCompleted
Pstoute\LaravelHosting\Events\DeploymentFailed

// Backup events
Pstoute\LaravelHosting\Events\BackupCreated
Pstoute\LaravelHosting\Events\BackupRestored
```

Listen to events in your `EventServiceProvider`:

```php
protected $listen = [
    \Pstoute\LaravelHosting\Events\DeploymentCompleted::class => [
        \App\Listeners\NotifyDeploymentComplete::class,
    ],
];
```

## Exception Handling

```php
use Pstoute\LaravelHosting\Exceptions\HostingException;
use Pstoute\LaravelHosting\Exceptions\ServerNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;
use Pstoute\LaravelHosting\Exceptions\AuthenticationException;
use Pstoute\LaravelHosting\Exceptions\RateLimitException;
use Pstoute\LaravelHosting\Exceptions\ProvisioningException;

try {
    $server = Hosting::getServer('invalid-id');
} catch (ServerNotFoundException $e) {
    // Server not found
} catch (AuthenticationException $e) {
    // Invalid API credentials
} catch (RateLimitException $e) {
    // Too many requests, retry after: $e->retryAfter
} catch (HostingException $e) {
    // Generic hosting error
}
```

## Testing

The package includes a `FakeHostingProvider` for testing your applications:

```php
use Pstoute\LaravelHosting\Testing\FakeHostingProvider;
use Pstoute\LaravelHosting\Facades\Hosting;
use Pstoute\LaravelHosting\Data\Server;

public function test_it_creates_a_server(): void
{
    $fake = new FakeHostingProvider();

    // Swap the provider
    Hosting::swap($fake);

    // Pre-seed data
    $fake->withServers([
        Server::fromArray([
            'id' => '1',
            'name' => 'existing-server',
            'status' => 'active',
        ]),
    ]);

    // Your test code
    $servers = Hosting::listServers();
    $this->assertCount(1, $servers);

    // Create a server
    $server = Hosting::createServer(['name' => 'new-server']);

    // Assertions
    $fake->assertServerCreated('new-server');
    $fake->assertMethodCalled('createServer');
    $fake->assertMethodNotCalled('deleteServer');
}

public function test_it_handles_failures(): void
{
    $fake = new FakeHostingProvider();
    $fake->shouldFailWith('Simulated API error');

    Hosting::swap($fake);

    $this->expectException(\Pstoute\LaravelHosting\Exceptions\HostingException::class);

    Hosting::listServers();
}
```

### Available Test Assertions

```php
$fake->assertServerCreated(?string $name = null);
$fake->assertSiteCreated(?string $domain = null);
$fake->assertSslInstalled(?string $siteId = null);
$fake->assertDeployed(?string $siteId = null);
$fake->assertBackupCreated(?string $siteId = null);
$fake->assertMethodCalled(string $method, ?int $times = null);
$fake->assertMethodNotCalled(string $method);
$fake->getRecordedCalls(); // Get all recorded method calls
$fake->getCallsTo(string $method); // Get calls to specific method
```

### Test Setup Methods

```php
$fake->withServers([...]); // Pre-populate servers
$fake->withSites([...]);   // Pre-populate sites
$fake->withDatabases([...]);
$fake->withCapabilities([Capability::ServerManagement]);
$fake->shouldFailWith('Error message');
$fake->notConfigured();
$fake->reset(); // Reset all state
```

## Caching

The package includes built-in caching to reduce API calls:

```php
// config/hosting.php
'cache' => [
    'enabled' => env('HOSTING_CACHE_ENABLED', true),
    'prefix' => 'hosting:',
    'ttl' => [
        'servers' => 300,       // 5 minutes
        'sites' => 300,         // 5 minutes
        'ssl' => 3600,          // 1 hour
        'databases' => 600,     // 10 minutes
        'deployments' => 0,     // Never cache
    ],
],
```

## Rate Limiting

Built-in rate limiting protects against API limits:

```php
// config/hosting.php
'rate_limits' => [
    'enabled' => env('HOSTING_RATE_LIMIT_ENABLED', true),
    'per_minute' => env('HOSTING_RATE_LIMIT_PER_MINUTE', 60),
],
```

## Extending with Custom Providers

Register custom providers in your service provider:

```php
use Pstoute\LaravelHosting\Facades\Hosting;

public function boot(): void
{
    Hosting::extend('custom', function ($app, $config) {
        return new CustomHostingProvider($config);
    });
}
```

## License

This package is open-source software licensed under the [MIT license](LICENSE).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Credits

- [Paul Stoute](https://github.com/pstoute)
