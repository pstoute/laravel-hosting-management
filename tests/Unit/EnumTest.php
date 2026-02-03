<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pstoute\LaravelHosting\Enums\BackupStatus;
use Pstoute\LaravelHosting\Enums\BackupType;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\DeploymentStatus;
use Pstoute\LaravelHosting\Enums\HostingProvider;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\ServerProvider;
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;

class EnumTest extends TestCase
{
    public function test_php_version_from_string(): void
    {
        $this->assertEquals(PhpVersion::PHP_83, PhpVersion::fromString('8.3'));
        $this->assertEquals(PhpVersion::PHP_83, PhpVersion::fromString('php8.3'));
        $this->assertEquals(PhpVersion::PHP_82, PhpVersion::fromString('8.2.15'));
        $this->assertNull(PhpVersion::fromString('invalid'));
        $this->assertNull(PhpVersion::fromString(null));
    }

    public function test_php_version_is_supported(): void
    {
        $this->assertTrue(PhpVersion::PHP_83->isSupported());
        $this->assertTrue(PhpVersion::PHP_82->isSupported());
        $this->assertFalse(PhpVersion::PHP_74->isSupported());
    }

    public function test_php_version_latest_and_recommended(): void
    {
        $this->assertEquals(PhpVersion::PHP_84, PhpVersion::latest());
        $this->assertEquals(PhpVersion::PHP_83, PhpVersion::recommended());
    }

    public function test_server_status_from_string(): void
    {
        $this->assertEquals(ServerStatus::Active, ServerStatus::fromString('active'));
        $this->assertEquals(ServerStatus::Active, ServerStatus::fromString('running'));
        $this->assertEquals(ServerStatus::Provisioning, ServerStatus::fromString('installing'));
        $this->assertEquals(ServerStatus::Unknown, ServerStatus::fromString('invalid'));
    }

    public function test_server_status_is_operational(): void
    {
        $this->assertTrue(ServerStatus::Active->isOperational());
        $this->assertFalse(ServerStatus::Provisioning->isOperational());
        $this->assertFalse(ServerStatus::Failed->isOperational());
    }

    public function test_site_status_from_string(): void
    {
        $this->assertEquals(SiteStatus::Active, SiteStatus::fromString('active'));
        $this->assertEquals(SiteStatus::Active, SiteStatus::fromString('enabled'));
        $this->assertEquals(SiteStatus::Suspended, SiteStatus::fromString('suspended'));
        $this->assertEquals(SiteStatus::Unknown, SiteStatus::fromString(null));
    }

    public function test_ssl_status_from_string(): void
    {
        $this->assertEquals(SslStatus::Active, SslStatus::fromString('active'));
        $this->assertEquals(SslStatus::Active, SslStatus::fromString('installed'));
        $this->assertEquals(SslStatus::None, SslStatus::fromString('none'));
        $this->assertEquals(SslStatus::Expired, SslStatus::fromString('expired'));
    }

    public function test_ssl_status_is_secure(): void
    {
        $this->assertTrue(SslStatus::Active->isSecure());
        $this->assertFalse(SslStatus::None->isSecure());
        $this->assertFalse(SslStatus::Expired->isSecure());
    }

    public function test_deployment_status_from_string(): void
    {
        $this->assertEquals(DeploymentStatus::Running, DeploymentStatus::fromString('deploying'));
        $this->assertEquals(DeploymentStatus::Succeeded, DeploymentStatus::fromString('success'));
        $this->assertEquals(DeploymentStatus::Failed, DeploymentStatus::fromString('error'));
    }

    public function test_deployment_status_is_complete(): void
    {
        $this->assertTrue(DeploymentStatus::Succeeded->isComplete());
        $this->assertTrue(DeploymentStatus::Failed->isComplete());
        $this->assertFalse(DeploymentStatus::Running->isComplete());
    }

    public function test_backup_status_from_string(): void
    {
        $this->assertEquals(BackupStatus::Completed, BackupStatus::fromString('completed'));
        $this->assertEquals(BackupStatus::Completed, BackupStatus::fromString('available'));
        $this->assertEquals(BackupStatus::InProgress, BackupStatus::fromString('creating'));
    }

    public function test_backup_type_from_string(): void
    {
        $this->assertEquals(BackupType::Full, BackupType::fromString('full'));
        $this->assertEquals(BackupType::Database, BackupType::fromString('database'));
        $this->assertEquals(BackupType::Files, BackupType::fromString('files'));
    }

    public function test_backup_type_includes_database(): void
    {
        $this->assertTrue(BackupType::Full->includesDatabase());
        $this->assertTrue(BackupType::Database->includesDatabase());
        $this->assertFalse(BackupType::Files->includesDatabase());
    }

    public function test_server_provider_from_string(): void
    {
        $this->assertEquals(ServerProvider::DigitalOcean, ServerProvider::fromString('digitalocean'));
        $this->assertEquals(ServerProvider::DigitalOcean, ServerProvider::fromString('do'));
        $this->assertEquals(ServerProvider::AWS, ServerProvider::fromString('aws'));
        $this->assertEquals(ServerProvider::Unknown, ServerProvider::fromString('invalid'));
    }

    public function test_hosting_provider_from_string(): void
    {
        $this->assertEquals(HostingProvider::Forge, HostingProvider::fromString('forge'));
        $this->assertEquals(HostingProvider::GridPane, HostingProvider::fromString('gridpane'));
        $this->assertEquals(HostingProvider::WPEngine, HostingProvider::fromString('wpengine'));
        $this->assertNull(HostingProvider::fromString('invalid'));
    }

    public function test_hosting_provider_api_urls(): void
    {
        $this->assertEquals('https://forge.laravel.com/api/v1', HostingProvider::Forge->apiBaseUrl());
        $this->assertEquals('https://my.gridpane.com/api/v1', HostingProvider::GridPane->apiBaseUrl());
    }

    public function test_capability_labels(): void
    {
        $this->assertEquals('Server Management', Capability::ServerManagement->label());
        $this->assertEquals('SSL Installation', Capability::SslInstallation->label());
        $this->assertEquals('Git Deployment', Capability::GitDeployment->label());
    }

    public function test_capability_all_returns_all_cases(): void
    {
        $all = Capability::all();
        $this->assertCount(count(Capability::cases()), $all);
    }
}
