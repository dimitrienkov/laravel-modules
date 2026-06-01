<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\Support;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Exceptions\CyclicDependencyException;
use DimitrienkoV\LaravelModules\Exceptions\DependentModulesExistException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyDisabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyMissingException;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleDependencyGuard::class)]
#[Group('lifecycle')]
final class ModuleDependencyGuardTest extends TestCase
{
    #[Test]
    public function assertGraphValidPassesWithSatisfiedDependencies(): void
    {
        $users = ModuleFactory::make(name: 'users', version: '1.5.0');
        $blog = ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']);

        $guard = $this->makeGuard([]);

        $guard->assertGraphValid([$users, $blog]);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertGraphValidThrowsOnMissingDependency(): void
    {
        $blog = ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']);
        $guard = $this->makeGuard([]);

        $this->expectException(ModuleDependencyMissingException::class);
        $guard->assertGraphValid([$blog]);
    }

    #[Test]
    public function assertGraphValidThrowsOnDisabledDependency(): void
    {
        $users = ModuleFactory::make(name: 'users', version: '2.0.0', enabled: false);
        $blog = ModuleFactory::make(name: 'blog', dependencies: ['users' => '^2.0']);

        $guard = $this->makeGuard([]);

        $this->expectException(ModuleDependencyDisabledException::class);
        $guard->assertGraphValid([$users, $blog]);
    }

    #[Test]
    public function assertGraphValidThrowsOnCyclicDependency(): void
    {
        $a = ModuleFactory::make(name: 'module_a', dependencies: ['module_b' => '*']);
        $b = ModuleFactory::make(name: 'module_b', dependencies: ['module_a' => '*']);

        $guard = $this->makeGuard([]);

        $this->expectException(CyclicDependencyException::class);
        $guard->assertGraphValid([$a, $b]);
    }

    #[Test]
    public function assertCanDisableBlockedByEnabledDependents(): void
    {
        $users = ModuleFactory::make(name: 'users', version: '1.0.0');
        $blog = ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']);

        $guard = $this->makeGuard([$users, $blog]);

        $this->expectException(DependentModulesExistException::class);
        $this->expectExceptionMessageMatches('/disable.*users.*blog/i');

        $guard->assertCanDisable($users);
    }

    #[Test]
    public function assertCanDisableAllowedWhenDependentIsDisabled(): void
    {
        $users = ModuleFactory::make(name: 'users', version: '1.0.0');
        $blog = ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '^1.0']);

        $guard = $this->makeGuard([$users, $blog]);

        $guard->assertCanDisable($users);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertCanRemoveBlockedByAnyInstalledDependent(): void
    {
        $users = ModuleFactory::make(name: 'users', version: '1.0.0');
        $blog = ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '^1.0']);

        $guard = $this->makeGuard([$users, $blog]);

        $this->expectException(DependentModulesExistException::class);
        $this->expectExceptionMessageMatches('/remove.*users.*blog/i');

        $guard->assertCanRemove($users);
    }

    #[Test]
    public function assertCanRemoveSucceedsWithNoDependents(): void
    {
        $users = ModuleFactory::make(name: 'users');
        $blog = ModuleFactory::make(name: 'blog');

        $guard = $this->makeGuard([$users, $blog]);

        $guard->assertCanRemove($users);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertCanDisableSortsDependentNames(): void
    {
        $users = ModuleFactory::make(name: 'users', version: '1.0.0');
        $blog = ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']);
        $forum = ModuleFactory::make(name: 'forum', dependencies: ['users' => '^1.0']);

        $guard = $this->makeGuard([$users, $blog, $forum]);

        try {
            $guard->assertCanDisable($users);
            $this->fail('Expected DependentModulesExistException');
        } catch (DependentModulesExistException $e) {
            $this->assertStringContainsString('blog, forum', $e->getMessage());
        }
    }

    /**
     * @param array<int, Module> $registryModules
     */
    private function makeGuard(array $registryModules): ModuleDependencyGuard
    {
        $registry = new class ($registryModules) implements ModuleRegistryInterface {
            /**
             * @param array<int, Module> $modules
             */
            public function __construct(private readonly array $modules)
            {
            }

            public function all(): array
            {
                return $this->modules;
            }

            public function find(string $name): Module
            {
                foreach ($this->modules as $module) {
                    if ($module->name === $name) {
                        return $module;
                    }
                }

                throw new \RuntimeException("Module [{$name}] not found.");
            }

            public function has(string $name): bool
            {
                foreach ($this->modules as $module) {
                    if ($module->name === $name) {
                        return true;
                    }
                }

                return false;
            }

            public function reset(): void
            {
            }
        };

        return new ModuleDependencyGuard($registry, new TopologicalSorter());
    }
}
