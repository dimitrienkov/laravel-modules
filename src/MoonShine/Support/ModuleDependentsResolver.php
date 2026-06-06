<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\MoonShine\Support;

use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;

/**
 * Read-only resolver of the modules that depend on a given module, used by the
 * admin UI to preventively block disable/remove controls and explain why.
 *
 * It mirrors {@see \DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard}
 * (disable is blocked by ENABLED dependents, removal by ANY dependent) by reading
 * the dependency graph directly — it does NOT re-implement the guard's business
 * decision. Enforcement still happens inside the lifecycle use cases; this only
 * drives UX. Names are returned as display names, deterministically sorted so
 * tooltips and tests never depend on registry iteration order.
 */
final readonly class ModuleDependentsResolver
{
    public function __construct(
        private ModuleRegistryInterface $registry,
    ) {}

    /**
     * Enabled modules that would break if the target were disabled
     * (mirrors {@see \DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard::assertCanDisable()}).
     *
     * @return list<string>
     */
    public function disableBlockers(string $moduleName): array
    {
        return $this->dependents($moduleName, onlyEnabled: true);
    }

    /**
     * Any modules that depend on the target, blocking its removal
     * (mirrors {@see \DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard::assertCanRemove()}).
     *
     * @return list<string>
     */
    public function removeBlockers(string $moduleName): array
    {
        return $this->dependents($moduleName, onlyEnabled: false);
    }

    /**
     * @return list<string>
     */
    private function dependents(string $moduleName, bool $onlyEnabled): array
    {
        $dependents = [];

        foreach ($this->registry->all() as $module) {
            if ($onlyEnabled && ! $module->isEnabled()) {
                continue;
            }

            if ($module->meta->dependencies->constraintFor($moduleName) !== null) {
                $dependents[] = $module->displayName;
            }
        }

        sort($dependents);

        return $dependents;
    }
}
