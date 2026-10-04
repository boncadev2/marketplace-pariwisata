<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PublicCatalogCache
{
    private const DESTINATIONS_VERSION_KEY = 'public-catalog:destinations:version';

    private const LOOKUPS_VERSION_KEY = 'public-catalog:lookups:version';

    private const PRODUCTS_VERSION_KEY = 'public-catalog:products:version';

    public function destinationsVersion(): int
    {
        return $this->version(self::DESTINATIONS_VERSION_KEY);
    }

    public function lookupsVersion(): int
    {
        return $this->version(self::LOOKUPS_VERSION_KEY);
    }

    public function productsVersion(): int
    {
        return $this->version(self::PRODUCTS_VERSION_KEY);
    }

    public function bumpDestinations(): void
    {
        $this->bump(self::DESTINATIONS_VERSION_KEY);
    }

    public function bumpLookups(): void
    {
        $this->bump(self::LOOKUPS_VERSION_KEY);
    }

    public function bumpProducts(): void
    {
        $this->bump(self::PRODUCTS_VERSION_KEY);
    }

    private function version(string $key): int
    {
        return (int) Cache::rememberForever($key, fn (): int => 1);
    }

    private function bump(string $key): void
    {
        $this->version($key);
        Cache::increment($key);
    }
}
