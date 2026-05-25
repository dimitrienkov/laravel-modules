<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;

final readonly class LifecycleRegistryInvalidator
{
    public function __construct(
        private ModuleRegistryCache $cache,
        private ModuleRegistry $registry,
    ) {
    }

    public function invalidate(): void
    {
        $this->cache->forget();
        $this->registry->reset();
    }
}
