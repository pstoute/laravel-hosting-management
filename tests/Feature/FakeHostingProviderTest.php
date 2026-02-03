<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Tests\Feature;

use Pstoute\LaravelHosting\Data\Server;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Enums\Capability;
use Pstoute\LaravelHosting\Enums\ServerStatus;
use Pstoute\LaravelHosting\Enums\SiteStatus;
use Pstoute\LaravelHosting\Exceptions\HostingException;
use Pstoute\LaravelHosting\Exceptions\ServerNotFoundException;
use Pstoute\LaravelHosting\Exceptions\SiteNotFoundException;
use Pstoute\LaravelHosting\Testing\FakeHostingProvider;
use Pstoute\LaravelHosting\Tests\TestCase;

class FakeHostingProviderTest extends TestCase
{
    private FakeHostingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FakeHostingProvider();
    }

    public function test_it_returns_provider_name(): void
    {
        $this->assertEquals('fake', $this->provider->getName());
        $this->assertEquals('Fake Provider', $this->provider->getDisplayName());
    }

    public function test_it_is_configured_by_default(): void
    {
        $this->assertTrue($this->provider->isConfigured());
    }

    public function test_connection_test_succeeds_by_default(): void
    {
        $result = $this->provider->testConnection();

        $this->assertTrue($result->success);
        $this->assertEquals('Connection successful', $result->message);
    }

    public function test_it_can_be_configured_to_fail_connection(): void
    {
        $this->provider->shouldFailWith('Simulated failure for testing');

        $this->expectException(HostingException::class);
        $this->expectExceptionMessage('Simulated failure for testing');

        $this->provider->testConnection();
    }

    public function test_it_supports_all_capabilities_by_default(): void
    {
        foreach (Capability::cases() as $capability) {
            $this->assertTrue($this->provider->supportsCapability($capability));
        }
    }

    public function test_it_can_have_capabilities_changed(): void
    {
        $this->provider->withCapabilities([Capability::ServerManagement]);

        $this->assertTrue($this->provider->supportsCapability(Capability::ServerManagement));
        $this->assertFalse($this->provider->supportsCapability(Capability::SslInstallation));
    }

    public function test_it_lists_seeded_servers(): void
    {
        $servers = [
            Server::fromArray([
                'id' => '1',
                'name' => 'server-1',
                'ip_address' => '1.1.1.1',
                'status' => 'active',
            ]),
            Server::fromArray([
                'id' => '2',
                'name' => 'server-2',
                'ip_address' => '2.2.2.2',
                'status' => 'active',
            ]),
        ];

        $this->provider->withServers($servers);

        $result = $this->provider->listServers();

        $this->assertCount(2, $result);
        $this->assertEquals('server-1', $result->first()->name);
    }

    public function test_it_can_get_a_specific_server(): void
    {
        $server = Server::fromArray([
            'id' => '123',
            'name' => 'test-server',
            'ip_address' => '1.2.3.4',
            'status' => 'active',
        ]);

        $this->provider->withServers([$server]);

        $result = $this->provider->getServer('123');

        $this->assertEquals('123', $result->id);
        $this->assertEquals('test-server', $result->name);
    }

    public function test_it_throws_when_server_not_found(): void
    {
        $this->expectException(ServerNotFoundException::class);

        $this->provider->getServer('non-existent');
    }

    public function test_it_can_create_a_server(): void
    {
        $config = [
            'name' => 'new-server',
            'provider' => 'digitalocean',
            'size' => 's-1vcpu-1gb',
            'region' => 'nyc1',
        ];

        $server = $this->provider->createServer($config);

        $this->assertEquals('new-server', $server->name);
        $this->assertEquals(ServerStatus::Provisioning, $server->status);
        $this->provider->assertServerCreated('new-server');
    }

    public function test_it_can_delete_a_server(): void
    {
        $server = Server::fromArray([
            'id' => '123',
            'name' => 'to-delete',
            'status' => 'active',
        ]);

        $this->provider->withServers([$server]);

        $result = $this->provider->deleteServer('123');

        $this->assertTrue($result);
        $this->provider->assertMethodCalled('deleteServer');

        $this->expectException(ServerNotFoundException::class);
        $this->provider->getServer('123');
    }

    public function test_it_lists_seeded_sites(): void
    {
        $sites = [
            Site::fromArray([
                'id' => '1',
                'server_id' => 'srv-1',
                'domain' => 'example.com',
                'status' => 'active',
            ]),
            Site::fromArray([
                'id' => '2',
                'server_id' => 'srv-1',
                'domain' => 'test.com',
                'status' => 'active',
            ]),
        ];

        $this->provider->withSites($sites);

        $allSites = $this->provider->listSites();
        $this->assertCount(2, $allSites);

        $filteredSites = $this->provider->listSites('srv-1');
        $this->assertCount(2, $filteredSites);

        $filteredSites = $this->provider->listSites('srv-2');
        $this->assertCount(0, $filteredSites);
    }

    public function test_it_can_get_a_specific_site(): void
    {
        $site = Site::fromArray([
            'id' => '456',
            'server_id' => '123',
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $this->provider->withSites([$site]);

        $result = $this->provider->getSite('456');

        $this->assertEquals('456', $result->id);
        $this->assertEquals('example.com', $result->domain);
    }

    public function test_it_throws_when_site_not_found(): void
    {
        $this->expectException(SiteNotFoundException::class);

        $this->provider->getSite('non-existent');
    }

    public function test_it_can_create_a_site(): void
    {
        $server = Server::fromArray([
            'id' => '123',
            'name' => 'test-server',
            'status' => 'active',
        ]);

        $this->provider->withServers([$server]);

        $config = [
            'domain' => 'newsite.com',
            'php_version' => '8.3',
        ];

        $site = $this->provider->createSite('123', $config);

        $this->assertEquals('newsite.com', $site->domain);
        $this->assertEquals('123', $site->serverId);
        $this->assertEquals(SiteStatus::Installing, $site->status);
        $this->provider->assertSiteCreated('newsite.com');
    }

    public function test_it_can_delete_a_site(): void
    {
        $site = Site::fromArray([
            'id' => '456',
            'server_id' => '123',
            'domain' => 'to-delete.com',
            'status' => 'active',
        ]);

        $this->provider->withSites([$site]);

        $result = $this->provider->deleteSite('456');

        $this->assertTrue($result);
        $this->provider->assertMethodCalled('deleteSite');
    }

    public function test_it_tracks_all_api_calls(): void
    {
        $this->provider->listServers();
        $this->provider->testConnection();

        $calls = $this->provider->getRecordedCalls();

        $this->assertCount(2, $calls);
        $this->assertEquals('listServers', $calls[0]['method']);
        $this->assertEquals('testConnection', $calls[1]['method']);
    }

    public function test_it_can_assert_method_was_called_specific_times(): void
    {
        $this->provider->listServers();
        $this->provider->listServers();
        $this->provider->listSites();

        $this->provider->assertMethodCalled('listServers', 2);
        $this->provider->assertMethodCalled('listSites', 1);
    }

    public function test_it_can_assert_method_was_not_called(): void
    {
        $this->provider->listServers();

        $this->provider->assertMethodNotCalled('listSites');
    }

    public function test_it_can_be_reset(): void
    {
        $server = Server::fromArray([
            'id' => '1',
            'name' => 'test',
            'status' => 'active',
        ]);

        $this->provider->withServers([$server]);
        $this->provider->listServers();

        $this->provider->reset();

        $this->assertCount(0, $this->provider->listServers());
        // After reset, one call was made by listServers() after reset
        $calls = $this->provider->getRecordedCalls();
        $this->assertCount(1, $calls);
        $this->assertTrue($this->provider->testConnection()->success);
    }

    public function test_it_can_install_ssl_certificate(): void
    {
        $site = Site::fromArray([
            'id' => '123',
            'server_id' => '1',
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $this->provider->withSites([$site]);

        $cert = $this->provider->installSslCertificate('123');

        $this->assertEquals('123', $cert->siteId);
        $this->provider->assertSslInstalled('123');
    }

    public function test_it_can_create_deployment(): void
    {
        $site = Site::fromArray([
            'id' => '123',
            'server_id' => '1',
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $this->provider->withSites([$site]);

        $deployment = $this->provider->deploy('123');

        $this->assertEquals('123', $deployment->siteId);
        $this->provider->assertDeployed('123');
    }

    public function test_it_can_create_backup(): void
    {
        $site = Site::fromArray([
            'id' => '123',
            'server_id' => '1',
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $this->provider->withSites([$site]);

        $backup = $this->provider->createBackup('123');

        $this->assertEquals('123', $backup->siteId);
        $this->provider->assertBackupCreated('123');
    }

    public function test_not_configured_provider(): void
    {
        $this->provider->notConfigured();

        $this->assertFalse($this->provider->isConfigured());
    }
}
