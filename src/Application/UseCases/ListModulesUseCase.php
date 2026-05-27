<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\ListModulesResult;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class ListModulesUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
    ) {
    }

    public function execute(?bool $enabledFilter = null, ?ModuleKind $kindFilter = null): ListModulesResult
    {
        $modules = $this->registry->all();

        if ($enabledFilter !== null) {
            $modules = array_filter(
                $modules,
                static fn (Module $m): bool => $m->isEnabled() === $enabledFilter,
            );
        }

        if ($kindFilter instanceof ModuleKind) {
            $modules = array_filter(
                $modules,
                static fn (Module $m): bool => $m->meta->kind === $kindFilter,
            );
        }

        return new ListModulesResult(array_values($modules));
    }
}
