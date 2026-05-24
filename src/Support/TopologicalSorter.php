<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use Composer\Semver\Semver;
use DimitrienkoV\LaravelModules\Exceptions\CyclicDependencyException;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyDisabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyIncompatibleException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyMissingException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class TopologicalSorter
{
    /**
     * @param array<int, Module> $modules
     *
     * @return array<int, Module>
     */
    public function sort(array $modules): array
    {
        $moduleMap = $this->moduleMap($modules);
        $sorted = [];
        $visiting = [];
        $visited = [];
        $stack = [];

        foreach (array_keys($moduleMap) as $moduleName) {
            $this->visit($moduleName, $moduleMap, $sorted, $visiting, $visited, $stack);
        }

        return array_values($sorted);
    }

    /**
     * @param array<int, Module> $modules
     *
     * @return array<string, Module>
     */
    private function moduleMap(array $modules): array
    {
        $moduleMap = [];

        foreach ($modules as $module) {
            if (isset($moduleMap[$module->name])) {
                throw InvalidManifestException::forPath(
                    $module->manifestPath(),
                    "duplicate module name [{$module->name}]."
                );
            }

            $moduleMap[$module->name] = $module;
        }

        ksort($moduleMap);

        return $moduleMap;
    }

    /**
     * @param array<string, Module> $moduleMap
     * @param array<string, Module> $sorted
     * @param array<string, bool>   $visiting
     * @param array<string, bool>   $visited
     * @param array<int, string>    $stack
     */
    private function visit(
        string $moduleName,
        array $moduleMap,
        array &$sorted,
        array &$visiting,
        array &$visited,
        array &$stack,
    ): void {
        if (isset($visited[$moduleName])) {
            return;
        }

        if (isset($visiting[$moduleName])) {
            $cycleStart = array_search($moduleName, $stack, true);
            $cycle = $cycleStart === false ? [$moduleName] : \array_slice($stack, $cycleStart);
            $cycle[] = $moduleName;

            throw CyclicDependencyException::forCycle($cycle);
        }

        $visiting[$moduleName] = true;
        $stack[] = $moduleName;

        $module = $moduleMap[$moduleName];
        foreach ($module->meta->dependencies->all() as $dependencyName => $constraint) {
            $dependency = $moduleMap[$dependencyName] ?? null;

            if ($module->isEnabled()) {
                if ($dependency === null) {
                    throw ModuleDependencyMissingException::forDependency($module->name, $dependencyName);
                }

                if (! $dependency->isEnabled()) {
                    throw ModuleDependencyDisabledException::forDependency($module->name, $dependencyName);
                }

                if (! Semver::satisfies($dependency->meta->version, $constraint)) {
                    throw ModuleDependencyIncompatibleException::forDependency(
                        $module->name,
                        $dependencyName,
                        $constraint,
                        $dependency->meta->version,
                    );
                }
            }

            if ($dependency !== null) {
                $this->visit($dependencyName, $moduleMap, $sorted, $visiting, $visited, $stack);
            }
        }

        array_pop($stack);
        unset($visiting[$moduleName]);
        $visited[$moduleName] = true;
        $sorted[$moduleName] = $module;
    }
}
