<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyEnabledException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use Throwable;

final readonly class EnableModuleUseCase
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

        if ($module->isEnabled()) {
            throw ModuleAlreadyEnabledException::forModule($moduleName);
        }

        $this->diagnostics->lifecycleStarted(LifecycleOperation::Enable, $moduleName);

        try {
            $candidateState = ModuleState::updatedFrom($module->state)->withEnabled(true);
            $candidate = $module->withState($candidateState);

            $allModules = $this->registry->all();
            $candidateGraph = array_map(
                static fn (Module $m): Module => $m->name === $moduleName ? $candidate : $m,
                $allModules,
            );

            $this->dependencyGuard->assertGraphValid($candidateGraph);

            $updated = $this->stateRepository->writeState($module, $candidateState);
            $this->invalidator->flushAndReset();

            $this->diagnostics->lifecycleSucceeded(LifecycleOperation::Enable, $moduleName);

            return $updated;
        } catch (Throwable $e) {
            $this->diagnostics->lifecycleFailed(LifecycleOperation::Enable, $moduleName, $e);

            throw $e;
        }
    }
}
