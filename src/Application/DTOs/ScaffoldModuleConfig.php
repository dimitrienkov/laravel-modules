<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;

final readonly class ScaffoldModuleConfig
{
    public function __construct(
        public string $name,
        public ?string $directory = null,
        public bool $enabled = true,
        public bool $force = false,
        public ?ModuleKind $kind = null,
    ) {
    }
}
