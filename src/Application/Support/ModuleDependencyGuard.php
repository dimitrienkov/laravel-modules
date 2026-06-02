<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\DependentModulesExistException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;

final readonly class ModuleDependencyGuard
{
    public function __construct(
        private ModuleRegistryInterface $registry,
        private TopologicalSorter $sorter,
    ) {}

    /**
     * @param array<int, Module> $candidateModules
     */
    public function assertGraphValid(array $candidateModules): void
    {
        $this->sorter->sort($candidateModules);
    }

    public function assertCanDisable(Module $module): void
    {
        $enabledDependents = $this->findDependents($module->name, onlyEnabled: true);

        if ($enabledDependents !== []) {
            throw DependentModulesExistException::forDisable($module->name, $enabledDependents);
        }
    }

    public function assertCanRemove(Module $module): void
    {
        $allDependents = $this->findDependents($module->name, onlyEnabled: false);

        if ($allDependents !== []) {
            throw DependentModulesExistException::forRemove($module->name, $allDependents);
        }
    }

    /**
     * @return list<string>
     */
    private function findDependents(string $moduleName, bool $onlyEnabled): array
    {
        $dependents = [];

        foreach ($this->registry->all() as $module) {
            if ($onlyEnabled && ! $module->isEnabled()) {
                continue;
            }

            if ($module->meta->dependencies->constraintFor($moduleName) !== null) {
                $dependents[] = $module->name;
            }
        }

        sort($dependents);

        return $dependents;
    }
}
