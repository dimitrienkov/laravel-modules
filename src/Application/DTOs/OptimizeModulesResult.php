<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

final readonly class OptimizeModulesResult
{
    public function __construct(
        public string $path,
        public int $count,
    ) {}
}
