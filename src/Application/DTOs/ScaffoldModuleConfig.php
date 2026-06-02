<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

use DimitrienkoV\LaravelModules\Application\Enums\ScaffoldComponent;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleGroup;

final readonly class ScaffoldModuleConfig
{
    /**
     * @param array<int, ScaffoldComponent>|null $components Explicit skeleton
     *                                                       selection. `null` keeps the default minimal skeleton; a list (even
     *                                                       empty) switches to component-driven scaffolding.
     */
    public function __construct(
        public string $name,
        public ?string $directory = null,
        public bool $enabled = true,
        public bool $force = false,
        public ?ModuleKind $kind = null,
        public ?ModuleGroup $group = null,
        public ?array $components = null,
    ) {
    }
}
