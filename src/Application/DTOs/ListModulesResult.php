<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\DTOs;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class ListModulesResult
{
    /**
     * @param list<Module> $modules
     */
    public function __construct(
        public array $modules,
    ) {}
}
