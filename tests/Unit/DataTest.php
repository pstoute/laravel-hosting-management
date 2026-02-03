<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Tests\Unit;

use PHPUnit\Framework\TestCase;
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
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Enums\SslStatus;

class DataTest extends TestCase
{
    public function test_server_from_array(): void
    {
        $server = Server::fromArray([
            'id' => '123',
            'name' => 'test-server',
            'ip_address' => '1.2.3.4',
            'status' => 'active',
            'php_version' => '8.3',
        ]);

        $this->assertEquals('123', $server->id);
        $this->assertEquals('test-server', $server->name);
        $this->assertEquals('1.2.3.4', $server->ipAddress);
        $this->assertEquals(ServerStatus::Active, $server->status);
        $this->assertTrue($server->isOperational());
    }

    public function test_server_to_array(): void
    {
        $server = Server::fromArray([
            'id' => '123',
            'name' => 'test-server',
            'status' => 'active',
        ]);

        $array = $server->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('123', $array['id']);
        $this->assertEquals('test-server', $array['name']);
        $this->assertEquals('active', $array['status']);
    }

    public function test_site_from_array(): void
    {
        $site = Site::fromArray([
            'id' => '456',
            'server_id' => '123',
            'domain' => 'example.com',
            'status' => 'active',
            'ssl_enabled' => true,
            'is_wordpress' => true,
        ]);

        $this->assertEquals('456', $site->id);
        $this->assertEquals('123', $site->serverId);
        $this->assertEquals('example.com', $site->domain);
        $this->assertEquals(SiteStatus::Active, $site->status);
        $this->assertTrue($site->sslEnabled);
        $this->assertTrue($site->isWordPress);
    }

    public function test_site_has_valid_ssl(): void
    {
        $siteWithSsl = Site::fromArray([
            'id' => '1',
            'server_id' => '1',
            'domain' => 'example.com',
            'ssl_enabled' => true,
            'ssl_status' => 'active',
        ]);

        $siteWithoutSsl = Site::fromArray([
            'id' => '2',
            'server_id' => '1',
            'domain' => 'example.org',
            'ssl_enabled' => false,
        ]);

        $this->assertTrue($siteWithSsl->hasValidSsl());
        $this->assertFalse($siteWithoutSsl->hasValidSsl());
    }

    public function test_database_from_array(): void
    {
        $database = Database::fromArray([
            'id' => '789',
            'name' => 'mydb',
            'server_id' => '123',
            'size_bytes' => 1073741824, // 1 GB
        ]);

        $this->assertEquals('789', $database->id);
        $this->assertEquals('mydb', $database->name);
        $this->assertEquals('1 GB', $database->humanReadableSize());
    }

    public function test_database_user_from_array(): void
    {
        $user = DatabaseUser::fromArray([
            'id' => '101',
            'username' => 'dbuser',
            'server_id' => '123',
            'databases' => ['db1', 'db2'],
        ]);

        $this->assertEquals('101', $user->id);
        $this->assertEquals('dbuser', $user->username);
        $this->assertTrue($user->hasAccessTo('db1'));
        $this->assertFalse($user->hasAccessTo('db3'));
    }

    public function test_system_user_from_array(): void
    {
        $user = SystemUser::fromArray([
            'id' => '201',
            'username' => 'sysuser',
            'server_id' => '123',
            'is_isolated' => true,
            'ssh_access' => true,
            'groups' => ['www-data', 'sudo'],
        ]);

        $this->assertEquals('201', $user->id);
        $this->assertEquals('sysuser', $user->username);
        $this->assertTrue($user->isIsolated);
        $this->assertTrue($user->hasSshAccess);
        $this->assertTrue($user->inGroup('sudo'));
        $this->assertFalse($user->inGroup('docker'));
    }

    public function test_ssl_certificate_is_valid(): void
    {
        $validCert = new SslCertificate(
            id: '1',
            siteId: '1',
            status: SslStatus::Active,
            expiresAt: new \DateTimeImmutable('+30 days'),
        );

        $expiredCert = new SslCertificate(
            id: '2',
            siteId: '2',
            status: SslStatus::Active,
            expiresAt: new \DateTimeImmutable('-1 day'),
        );

        $this->assertTrue($validCert->isValid());
        $this->assertFalse($expiredCert->isValid());
        $this->assertTrue($expiredCert->isExpired());
    }

    public function test_ssl_certificate_expires_soon(): void
    {
        $cert = new SslCertificate(
            id: '1',
            siteId: '1',
            status: SslStatus::Active,
            expiresAt: new \DateTimeImmutable('+15 days'),
        );

        $this->assertTrue($cert->expiresSoon(30));
        $this->assertFalse($cert->expiresSoon(10));
    }

    public function test_deployment_from_array(): void
    {
        $deployment = Deployment::fromArray([
            'id' => '301',
            'site_id' => '1',
            'status' => 'success',
            'commit_hash' => 'abc123',
            'started_at' => '2025-01-01 10:00:00',
            'finished_at' => '2025-01-01 10:05:00',
        ]);

        $this->assertEquals('301', $deployment->id);
        $this->assertTrue($deployment->isSuccessful());
        $this->assertTrue($deployment->isComplete());
        $this->assertEquals(300, $deployment->durationSeconds);
        $this->assertEquals('5m 0s', $deployment->humanReadableDuration());
    }

    public function test_backup_from_array(): void
    {
        $backup = Backup::fromArray([
            'id' => '401',
            'site_id' => '1',
            'status' => 'completed',
            'type' => 'full',
            'size_bytes' => 536870912, // 512 MB
        ]);

        $this->assertEquals('401', $backup->id);
        $this->assertTrue($backup->isComplete());
        $this->assertEquals('512 MB', $backup->humanReadableSize());
    }

    public function test_server_metrics_from_array(): void
    {
        $metrics = ServerMetrics::fromArray([
            'cpu_usage' => 75.5,
            'memory_usage' => 60.0,
            'disk_usage' => 45.0,
            'uptime_seconds' => 86400 * 30, // 30 days
        ]);

        $this->assertEquals(75.5, $metrics->cpuUsage);
        $this->assertEquals(60.0, $metrics->memoryUsage);
        $this->assertFalse($metrics->isCpuCritical());
        $this->assertFalse($metrics->isMemoryCritical());
        $this->assertStringContainsString('30d', $metrics->humanReadableUptime());
    }

    public function test_connection_result_success(): void
    {
        $result = ConnectionResult::success('Connected', ['test' => 'data'], 150);

        $this->assertTrue($result->success);
        $this->assertEquals('Connected', $result->message);
        $this->assertEquals(150, $result->latencyMs);
        $this->assertEquals(['test' => 'data'], $result->data);
    }

    public function test_connection_result_failure(): void
    {
        $result = ConnectionResult::failure('Connection failed', 401);

        $this->assertFalse($result->success);
        $this->assertEquals('Connection failed', $result->message);
        $this->assertEquals(401, $result->statusCode);
    }
}
