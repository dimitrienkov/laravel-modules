<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

final readonly class ScaffoldModuleConfig
{
    public function __construct(
        public string $name,
        public ?string $directory = null,
        public bool $enabled = true,
        public bool $force = false,
    ) {
    }
}
