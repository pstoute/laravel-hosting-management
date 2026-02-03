<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data\Collections;

use Illuminate\Support\Collection;
use Pstoute\LaravelHosting\Data\Server;
use Pstoute\LaravelHosting\Enums\ServerProvider;
use Pstoute\LaravelHosting\Enums\ServerStatus;

/**
 * @extends Collection<int, Server>
 */
class ServerCollection extends Collection
{
    /**
     * Create a new collection from an array of server data
     *
     * @param array<array<string, mixed>> $items
     */
    public static function fromArray(array $items): self
    {
        return new self(array_map(fn (array $data) => Server::fromArray($data), $items));
    }

    /**
     * Get only operational servers
     */
    public function operational(): self
    {
        return $this->filter(fn (Server $server) => $server->isOperational());
    }

    /**
     * Get only servers with pending status
     */
    public function pending(): self
    {
        return $this->filter(fn (Server $server) => $server->isPending());
    }

    /**
     * Filter by server status
     */
    public function whereStatus(ServerStatus $status): self
    {
        return $this->filter(fn (Server $server) => $server->status === $status);
    }

    /**
     * Filter by server provider (cloud provider)
     */
    public function whereProvider(ServerProvider $provider): self
    {
        return $this->filter(fn (Server $server) => $server->serverProvider === $provider);
    }

    /**
     * Filter by region
     */
    public function whereRegion(string $region): self
    {
        return $this->filter(fn (Server $server) => $server->region === $region);
    }

    /**
     * Sort by server name
     */
    public function sortByName(bool $descending = false): self
    {
        return $this->sortBy(fn (Server $server) => $server->name, SORT_NATURAL, $descending);
    }

    /**
     * Sort by creation date
     */
    public function sortByCreatedAt(bool $descending = true): self
    {
        return $this->sortBy(
            fn (Server $server) => $server->createdAt?->getTimestamp() ?? 0,
            SORT_NUMERIC,
            $descending
        );
    }

    /**
     * Find a server by ID
     */
    public function findById(string $id): ?Server
    {
        return $this->first(fn (Server $server) => $server->id === $id);
    }

    /**
     * Find a server by IP address
     */
    public function findByIp(string $ip): ?Server
    {
        return $this->first(fn (Server $server) => $server->ipAddress === $ip || $server->privateIpAddress === $ip);
    }

    /**
     * Find a server by name
     */
    public function findByName(string $name): ?Server
    {
        return $this->first(fn (Server $server) => $server->name === $name);
    }

    /**
     * Get unique regions
     *
     * @return array<string>
     */
    public function uniqueRegions(): array
    {
        return $this
            ->pluck('region')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get unique server providers
     *
     * @return array<ServerProvider>
     */
    public function uniqueProviders(): array
    {
        return $this
            ->pluck('serverProvider')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
