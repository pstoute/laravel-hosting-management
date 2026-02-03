<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

/**
 * Hosting management platforms/providers
 */
enum HostingProvider: string
{
    case Forge = 'forge';
    case GridPane = 'gridpane';
    case Cloudways = 'cloudways';
    case Kinsta = 'kinsta';
    case WPEngine = 'wpengine';
    case Ploi = 'ploi';
    case RunCloud = 'runcloud';
    case SpinupWP = 'spinupwp';
    case CPanel = 'cpanel';

    public function label(): string
    {
        return match ($this) {
            self::Forge => 'Laravel Forge',
            self::GridPane => 'GridPane',
            self::Cloudways => 'Cloudways',
            self::Kinsta => 'Kinsta',
            self::WPEngine => 'WP Engine',
            self::Ploi => 'Ploi',
            self::RunCloud => 'RunCloud',
            self::SpinupWP => 'SpinupWP',
            self::CPanel => 'cPanel/WHM',
        };
    }

    public function website(): string
    {
        return match ($this) {
            self::Forge => 'https://forge.laravel.com',
            self::GridPane => 'https://gridpane.com',
            self::Cloudways => 'https://cloudways.com',
            self::Kinsta => 'https://kinsta.com',
            self::WPEngine => 'https://wpengine.com',
            self::Ploi => 'https://ploi.io',
            self::RunCloud => 'https://runcloud.io',
            self::SpinupWP => 'https://spinupwp.com',
            self::CPanel => 'https://cpanel.net',
        };
    }

    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::Forge => 'https://forge.laravel.com/api/v1',
            self::GridPane => 'https://my.gridpane.com/api/v1',
            self::Cloudways => 'https://api.cloudways.com/api/v1',
            self::Kinsta => 'https://api.kinsta.com/v2',
            self::WPEngine => 'https://api.wpengineapi.com/v1',
            self::Ploi => 'https://ploi.io/api',
            self::RunCloud => 'https://manage.runcloud.io/api/v2',
            self::SpinupWP => 'https://api.spinupwp.com/v1',
            self::CPanel => '', // cPanel URL is per-server
        };
    }

    public function isWordPressOnly(): bool
    {
        return match ($this) {
            self::GridPane, self::Kinsta, self::WPEngine, self::SpinupWP => true,
            default => false,
        };
    }

    public function defaultRateLimit(): int
    {
        return match ($this) {
            self::Forge => 30,
            self::GridPane => 10,
            self::Cloudways => 30,
            self::Kinsta => 60,
            self::WPEngine => 60,
            self::Ploi => 60,
            self::RunCloud => 60,
            self::SpinupWP => 60,
            self::CPanel => 30,
        };
    }

    public static function fromString(?string $provider): ?self
    {
        if ($provider === null) {
            return null;
        }

        $normalized = strtolower(trim($provider));

        return match ($normalized) {
            'forge', 'laravel_forge', 'laravel-forge' => self::Forge,
            'gridpane', 'grid_pane', 'grid-pane' => self::GridPane,
            'cloudways' => self::Cloudways,
            'kinsta' => self::Kinsta,
            'wpengine', 'wp_engine', 'wp-engine', 'wpe' => self::WPEngine,
            'ploi' => self::Ploi,
            'runcloud', 'run_cloud', 'run-cloud' => self::RunCloud,
            'spinupwp', 'spinup_wp', 'spinup-wp', 'spinup' => self::SpinupWP,
            'cpanel', 'whm', 'cpanel/whm' => self::CPanel,
            default => self::tryFrom($normalized),
        };
    }
}
