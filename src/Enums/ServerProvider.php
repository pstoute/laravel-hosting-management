<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

/**
 * Cloud/infrastructure providers where servers can be hosted
 */
enum ServerProvider: string
{
    case DigitalOcean = 'digitalocean';
    case AWS = 'aws';
    case Vultr = 'vultr';
    case Linode = 'linode';
    case Hetzner = 'hetzner';
    case UpCloud = 'upcloud';
    case GoogleCloud = 'gcp';
    case Azure = 'azure';
    case Custom = 'custom';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::DigitalOcean => 'DigitalOcean',
            self::AWS => 'Amazon Web Services',
            self::Vultr => 'Vultr',
            self::Linode => 'Linode',
            self::Hetzner => 'Hetzner',
            self::UpCloud => 'UpCloud',
            self::GoogleCloud => 'Google Cloud',
            self::Azure => 'Microsoft Azure',
            self::Custom => 'Custom Server',
            self::Unknown => 'Unknown',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::DigitalOcean => 'DO',
            self::AWS => 'AWS',
            self::Vultr => 'Vultr',
            self::Linode => 'Linode',
            self::Hetzner => 'Hetzner',
            self::UpCloud => 'UpCloud',
            self::GoogleCloud => 'GCP',
            self::Azure => 'Azure',
            self::Custom => 'Custom',
            self::Unknown => 'N/A',
        };
    }

    public static function fromString(?string $provider): self
    {
        if ($provider === null) {
            return self::Unknown;
        }

        $normalized = strtolower(trim($provider));

        return match ($normalized) {
            'digitalocean', 'digital_ocean', 'do', 'ocean' => self::DigitalOcean,
            'aws', 'amazon', 'ec2', 'amazon_web_services' => self::AWS,
            'vultr' => self::Vultr,
            'linode', 'akamai' => self::Linode,
            'hetzner', 'hcloud' => self::Hetzner,
            'upcloud' => self::UpCloud,
            'gcp', 'google', 'google_cloud', 'googlecloud' => self::GoogleCloud,
            'azure', 'microsoft', 'microsoft_azure' => self::Azure,
            'custom', 'other', 'self-hosted', 'selfhosted' => self::Custom,
            default => self::tryFrom($normalized) ?? self::Unknown,
        };
    }
}
