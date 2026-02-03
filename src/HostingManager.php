<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting;

use Closure;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use Pstoute\LaravelHosting\Contracts\HostingProviderInterface;
use Pstoute\LaravelHosting\Providers\CloudwaysProvider;
use Pstoute\LaravelHosting\Providers\CPanelProvider;
use Pstoute\LaravelHosting\Providers\ForgeProvider;
use Pstoute\LaravelHosting\Providers\GridPaneProvider;
use Pstoute\LaravelHosting\Providers\KinstaProvider;
use Pstoute\LaravelHosting\Providers\PloiProvider;
use Pstoute\LaravelHosting\Providers\RunCloudProvider;
use Pstoute\LaravelHosting\Providers\SpinupWPProvider;
use Pstoute\LaravelHosting\Providers\WPEngineProvider;

class HostingManager extends Manager
{
    /**
     * Custom provider creators.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('hosting.default', 'forge');
    }

    /**
     * Get a hosting provider instance.
     *
     * @param string|null $name
     * @return HostingProviderInterface
     */
    public function driver($name = null): HostingProviderInterface
    {
        return parent::driver($name);
    }

    /**
     * Create a Forge provider instance.
     */
    protected function createForgeDriver(): ForgeProvider
    {
        $config = $this->getProviderConfig('forge');

        return new ForgeProvider($config);
    }

    /**
     * Create a GridPane provider instance.
     */
    protected function createGridpaneDriver(): GridPaneProvider
    {
        $config = $this->getProviderConfig('gridpane');

        return new GridPaneProvider($config);
    }

    /**
     * Create a Cloudways provider instance.
     */
    protected function createCloudwaysDriver(): CloudwaysProvider
    {
        $config = $this->getProviderConfig('cloudways');

        return new CloudwaysProvider($config);
    }

    /**
     * Create a Kinsta provider instance.
     */
    protected function createKinstaDriver(): KinstaProvider
    {
        $config = $this->getProviderConfig('kinsta');

        return new KinstaProvider($config);
    }

    /**
     * Create a WP Engine provider instance.
     */
    protected function createWpengineDriver(): WPEngineProvider
    {
        $config = $this->getProviderConfig('wpengine');

        return new WPEngineProvider($config);
    }

    /**
     * Create a Ploi provider instance.
     */
    protected function createPloiDriver(): PloiProvider
    {
        $config = $this->getProviderConfig('ploi');

        return new PloiProvider($config);
    }

    /**
     * Create a RunCloud provider instance.
     */
    protected function createRuncloudDriver(): RunCloudProvider
    {
        $config = $this->getProviderConfig('runcloud');

        return new RunCloudProvider($config);
    }

    /**
     * Create a SpinupWP provider instance.
     */
    protected function createSpinupwpDriver(): SpinupWPProvider
    {
        $config = $this->getProviderConfig('spinupwp');

        return new SpinupWPProvider($config);
    }

    /**
     * Create a cPanel provider instance.
     */
    protected function createCpanelDriver(): CPanelProvider
    {
        $config = $this->getProviderConfig('cpanel');

        return new CPanelProvider($config);
    }

    /**
     * Get the configuration for a provider.
     *
     * @return array<string, mixed>
     */
    protected function getProviderConfig(string $name): array
    {
        $config = $this->config->get("hosting.providers.{$name}", []);

        // Merge global cache settings
        $cacheConfig = $this->config->get('hosting.cache', []);
        $config['cache_enabled'] = $cacheConfig['enabled'] ?? true;
        $config['cache_ttl'] = $cacheConfig['ttl']['servers'] ?? 300;

        return $config;
    }

    /**
     * Register a custom provider creator.
     *
     * @param string $driver
     * @param Closure $callback
     * @return $this
     */
    public function extend($driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return HostingProviderInterface
     *
     * @throws InvalidArgumentException
     */
    protected function createDriver($driver): HostingProviderInterface
    {
        // Check for custom creators first
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        // Then check for built-in drivers
        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [{$driver}] not supported.");
    }

    /**
     * Call a custom driver creator.
     */
    protected function callCustomCreator(string $driver): HostingProviderInterface
    {
        $config = $this->getProviderConfig($driver);

        return $this->customCreators[$driver]($this->container, $config);
    }

    /**
     * Get all available provider names.
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return [
            'forge',
            'gridpane',
            'cloudways',
            'kinsta',
            'wpengine',
            'ploi',
            'runcloud',
            'spinupwp',
            'cpanel',
            ...array_keys($this->customCreators),
        ];
    }

    /**
     * Check if a provider is configured.
     */
    public function isConfigured(string $provider): bool
    {
        try {
            return $this->driver($provider)->isConfigured();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get all configured providers.
     *
     * @return array<string, HostingProviderInterface>
     */
    public function getConfiguredProviders(): array
    {
        $configured = [];

        foreach ($this->getAvailableProviders() as $provider) {
            if ($this->isConfigured($provider)) {
                $configured[$provider] = $this->driver($provider);
            }
        }

        return $configured;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array<mixed> $parameters
     * @return mixed
     */
    public function __call($method, $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
