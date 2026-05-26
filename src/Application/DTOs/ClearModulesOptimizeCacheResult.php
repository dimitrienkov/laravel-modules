<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

final readonly class ClearModulesOptimizeCacheResult
{
    public function __construct(
        public bool $cleared,
    ) {
    }
}
