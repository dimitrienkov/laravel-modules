<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryCacheInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;

final readonly class LifecycleRegistryInvalidator
{
    public function __construct(
        private ModuleRegistryCacheInterface $cache,
        private ModuleRegistryInterface $registry,
    ) {}

    public function flushAndReset(): void
    {
        try {
            $this->cache->forget();
        } finally {
            $this->registry->reset();
        }
    }
}
