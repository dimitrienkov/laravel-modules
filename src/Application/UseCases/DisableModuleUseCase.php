<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyDisabledException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;

final readonly class DisableModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleStateRepositoryInterface $stateRepository,
        private ModuleDependencyGuard $dependencyGuard,
        private LifecycleRegistryInvalidator $invalidator,
        private ModuleDiagnosticsInterface $diagnostics = new NullModuleDiagnostics(),
    ) {
    }

    public function execute(string $moduleName): Module
    {
        $module = $this->registry->find($moduleName);

        if (! $module->isEnabled()) {
            throw ModuleAlreadyDisabledException::forModule($moduleName);
        }

        $this->dependencyGuard->assertCanDisable($module);

        $this->diagnostics->lifecycleStarted(LifecycleOperation::Disable, $moduleName);

        $newState = ModuleState::updatedFrom($module->state)->withEnabled(false);

        $updated = $this->stateRepository->writeState($module, $newState);
        $this->invalidator->flushAndReset();

        $this->diagnostics->lifecycleSucceeded(LifecycleOperation::Disable, $moduleName);

        return $updated;
    }
}
