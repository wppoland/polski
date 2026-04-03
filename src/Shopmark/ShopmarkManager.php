<?php

declare(strict_types=1);

namespace Polski\Shopmark;

/**
 * Registry for all display elements (shopmarks) shown on product pages, loops, cart, etc.
 */
final class ShopmarkManager
{
    /** @var array<string, Shopmark> */
    private array $shopmarks = [];

    public function register(Shopmark $shopmark): void
    {
        $this->shopmarks[$shopmark->id] = $shopmark;
    }

    public function unregister(string $id): void
    {
        unset($this->shopmarks[$id]);
    }

    /**
     * @return list<Shopmark>
     */
    public function getForLocation(Location $location): array
    {
        $filtered = array_filter(
            $this->shopmarks,
            static fn (Shopmark $s) => $s->location === $location && $s->enabled,
        );

        usort($filtered, static fn (Shopmark $a, Shopmark $b) => $a->priority <=> $b->priority);

        return array_values($filtered);
    }

    public function isEnabled(string $id): bool
    {
        return isset($this->shopmarks[$id]) && $this->shopmarks[$id]->enabled;
    }

    /**
     * @return array<string, Shopmark>
     */
    public function all(): array
    {
        return $this->shopmarks;
    }
}
