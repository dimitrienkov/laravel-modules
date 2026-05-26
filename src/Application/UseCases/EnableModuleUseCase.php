<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyEnabledException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;

final readonly class EnableModuleUseCase
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private ModuleStateRepositoryInterface $stateRepository,
        private ModuleDependencyGuard $dependencyGuard,
        private LifecycleRegistryInvalidator $invalidator,
    ) {
    }

    public function execute(string $moduleName): Module
    {
        $module = $this->registry->find($moduleName);

        if ($module->isEnabled()) {
            throw ModuleAlreadyEnabledException::forModule($moduleName);
        }

        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        $candidateState = new ModuleState(
            enabled: true,
            installedAt: $module->state->installedAt ?? $now,
            updatedAt: $now,
        );
        $candidate = $module->withState($candidateState);

        $allModules = $this->registry->all();
        $candidateGraph = array_map(
            static fn (Module $m): Module => $m->name === $moduleName ? $candidate : $m,
            $allModules,
        );

        $this->dependencyGuard->assertGraphValid($candidateGraph);

        $updated = $this->stateRepository->writeState($module, $candidateState);
        $this->invalidator->invalidate();

        return $updated;
    }
}
