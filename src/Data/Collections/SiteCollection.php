<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data\Collections;

use Illuminate\Support\Collection;
use Pstoute\LaravelHosting\Data\Site;
use Pstoute\LaravelHosting\Enums\PhpVersion;
use Pstoute\LaravelHosting\Enums\SiteStatus;

/**
 * @extends Collection<int, Site>
 */
class SiteCollection extends Collection
{
    /**
     * Create a new collection from an array of site data
     *
     * @param array<array<string, mixed>> $items
     */
    public static function fromArray(array $items): self
    {
        return new self(array_map(fn (array $data) => Site::fromArray($data), $items));
    }

    /**
     * Get only operational sites
     */
    public function operational(): self
    {
        return $this->filter(fn (Site $site) => $site->isOperational());
    }

    /**
     * Get only sites with pending status
     */
    public function pending(): self
    {
        return $this->filter(fn (Site $site) => $site->isPending());
    }

    /**
     * Filter by site status
     */
    public function whereStatus(SiteStatus $status): self
    {
        return $this->filter(fn (Site $site) => $site->status === $status);
    }

    /**
     * Get only WordPress sites
     */
    public function wordpress(): self
    {
        return $this->filter(fn (Site $site) => $site->isWordPress);
    }

    /**
     * Get only non-WordPress sites
     */
    public function nonWordPress(): self
    {
        return $this->filter(fn (Site $site) => !$site->isWordPress);
    }

    /**
     * Get only sites with valid SSL
     */
    public function withSsl(): self
    {
        return $this->filter(fn (Site $site) => $site->hasValidSsl());
    }

    /**
     * Get only sites without SSL or invalid SSL
     */
    public function withoutSsl(): self
    {
        return $this->filter(fn (Site $site) => !$site->hasValidSsl());
    }

    /**
     * Get only staging sites
     */
    public function staging(): self
    {
        return $this->filter(fn (Site $site) => $site->isStaging);
    }

    /**
     * Get only production sites (non-staging)
     */
    public function production(): self
    {
        return $this->filter(fn (Site $site) => !$site->isStaging);
    }

    /**
     * Filter by server ID
     */
    public function forServer(string $serverId): self
    {
        return $this->filter(fn (Site $site) => $site->serverId === $serverId);
    }

    /**
     * Filter by PHP version
     */
    public function wherePhpVersion(PhpVersion $version): self
    {
        return $this->filter(fn (Site $site) => $site->phpVersion === $version);
    }

    /**
     * Sort by domain name
     */
    public function sortByDomain(bool $descending = false): self
    {
        return $this->sortBy(fn (Site $site) => $site->domain, SORT_NATURAL, $descending);
    }

    /**
     * Sort by creation date
     */
    public function sortByCreatedAt(bool $descending = true): self
    {
        return $this->sortBy(
            fn (Site $site) => $site->createdAt?->getTimestamp() ?? 0,
            SORT_NUMERIC,
            $descending
        );
    }

    /**
     * Sort by last deployment date
     */
    public function sortByLastDeployed(bool $descending = true): self
    {
        return $this->sortBy(
            fn (Site $site) => $site->lastDeployedAt?->getTimestamp() ?? 0,
            SORT_NUMERIC,
            $descending
        );
    }

    /**
     * Find a site by ID
     */
    public function findById(string $id): ?Site
    {
        return $this->first(fn (Site $site) => $site->id === $id);
    }

    /**
     * Find a site by domain
     */
    public function findByDomain(string $domain): ?Site
    {
        $normalized = strtolower($domain);
        return $this->first(function (Site $site) use ($normalized) {
            if (strtolower($site->domain) === $normalized) {
                return true;
            }
            foreach ($site->aliases as $alias) {
                if (strtolower($alias) === $normalized) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Get unique domains (including aliases)
     *
     * @return array<string>
     */
    public function uniqueDomains(): array
    {
        $domains = [];

        foreach ($this->items as $site) {
            $domains[] = $site->domain;
            foreach ($site->aliases as $alias) {
                $domains[] = $alias;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * Get unique server IDs
     *
     * @return array<string>
     */
    public function uniqueServerIds(): array
    {
        return $this
            ->pluck('serverId')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get unique PHP versions in use
     *
     * @return array<PhpVersion>
     */
    public function uniquePhpVersions(): array
    {
        return $this
            ->pluck('phpVersion')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Group sites by server ID
     *
     * @return Collection<string, self>
     */
    public function groupByServer(): Collection
    {
        return $this->groupBy('serverId')->map(fn ($items) => new self($items->all()));
    }
}
