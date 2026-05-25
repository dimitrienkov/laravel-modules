<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

final readonly class ScaffoldModuleResult
{
    public function __construct(
        public string $name,
        public string $path,
        public bool $enabled,
        public string $providerClass,
    ) {
    }
}
