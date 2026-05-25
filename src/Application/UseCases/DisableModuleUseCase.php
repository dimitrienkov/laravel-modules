<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyDisabledException;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class DisableModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleManifestRepositoryInterface $manifestRepository,
        private ModuleDependencyGuard $dependencyGuard,
        private LifecycleRegistryInvalidator $invalidator,
    ) {
    }

    public function execute(string $moduleName): Module
    {
        $module = $this->registry->find($moduleName);

        if (! $module->isEnabled()) {
            throw ModuleAlreadyDisabledException::forModule($moduleName);
        }

        $this->dependencyGuard->assertCanDisable($module);

        $newState = new ManifestState(
            enabled: false,
            installedAt: $module->state->installedAt,
            updatedAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        );

        $updated = $this->manifestRepository->updateState($module, $newState);
        $this->invalidator->invalidate();

        return $updated;
    }
}
