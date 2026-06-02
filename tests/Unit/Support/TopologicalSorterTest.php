<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\CyclicDependencyException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyDisabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyIncompatibleException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyMissingException;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopologicalSorter::class)]
#[Group('support')]
final class TopologicalSorterTest extends TestCase
{
    #[Test]
    public function sortsModulesAfterTheirDependencies(): void
    {
        $users = ModuleFactory::make(name: 'users', version: '1.2.0');
        $blog = ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']);

        $sorted = (new TopologicalSorter())->sort([$blog, $users]);

        self::assertSame(['users', 'blog'], array_map(
            static fn($module): string => $module->name,
            $sorted,
        ));
    }

    #[Test]
    public function failsForMissingDependencies(): void
    {
        $this->expectException(ModuleDependencyMissingException::class);
        $this->expectExceptionMessage('requires missing dependency [users]');

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']),
        ]);
    }

    #[Test]
    public function failsForDisabledDependencies(): void
    {
        $this->expectException(ModuleDependencyDisabledException::class);
        $this->expectExceptionMessage('requires disabled dependency [users]');

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']),
            ModuleFactory::make(name: 'users', enabled: false),
        ]);
    }

    #[Test]
    public function failsForIncompatibleDependencies(): void
    {
        $this->expectException(ModuleDependencyIncompatibleException::class);
        $this->expectExceptionMessage('matching [^1.0]');

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']),
            ModuleFactory::make(name: 'users', version: '2.0.0'),
        ]);
    }

    #[Test]
    public function failsForDependencyCycles(): void
    {
        $this->expectException(CyclicDependencyException::class);
        $this->expectExceptionMessage('blog -> users -> blog');

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', dependencies: ['users' => '*']),
            ModuleFactory::make(name: 'users', dependencies: ['blog' => '*']),
        ]);
    }

    #[Test]
    public function disabledModuleWithMissingDependencyDoesNotThrow(): void
    {
        $sorted = (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '^1.0']),
        ]);

        self::assertSame(['blog'], array_map(
            static fn($module): string => $module->name,
            $sorted,
        ));
    }

    #[Test]
    public function disabledModuleWithDisabledDependencyDoesNotThrow(): void
    {
        $sorted = (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '^1.0']),
            ModuleFactory::make(name: 'users', enabled: false, version: '1.2.0'),
        ]);

        self::assertCount(2, $sorted);
    }

    #[Test]
    public function disabledModuleWithIncompatibleDependencyDoesNotThrow(): void
    {
        $sorted = (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '^1.0']),
            ModuleFactory::make(name: 'users', version: '2.0.0'),
        ]);

        self::assertCount(2, $sorted);
    }

    #[Test]
    public function enabledModuleWithMissingDependencyStillThrows(): void
    {
        $this->expectException(ModuleDependencyMissingException::class);

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: true, dependencies: ['users' => '^1.0']),
        ]);
    }

    #[Test]
    public function disabledModulesWithCycleStillThrows(): void
    {
        $this->expectException(CyclicDependencyException::class);

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '*']),
            ModuleFactory::make(name: 'users', enabled: false, dependencies: ['blog' => '*']),
        ]);
    }

    #[Test]
    public function handlesDiamondDependency(): void
    {
        $d = ModuleFactory::make(name: 'd', version: '1.0.0');
        $b = ModuleFactory::make(name: 'b', version: '1.0.0', dependencies: ['d' => '*']);
        $c = ModuleFactory::make(name: 'c', version: '1.0.0', dependencies: ['d' => '*']);
        $a = ModuleFactory::make(name: 'a', version: '1.0.0', dependencies: ['b' => '*', 'c' => '*']);

        $sorted = (new TopologicalSorter())->sort([$a, $b, $c, $d]);

        $names = array_map(static fn($module): string => $module->name, $sorted);

        $posD = array_search('d', $names, true);
        $posB = array_search('b', $names, true);
        $posC = array_search('c', $names, true);
        $posA = array_search('a', $names, true);

        self::assertLessThan($posB, $posD);
        self::assertLessThan($posC, $posD);
        self::assertLessThan($posA, $posB);
        self::assertLessThan($posA, $posC);
    }

    #[Test]
    public function handlesThreeLevelDeepChain(): void
    {
        $d = ModuleFactory::make(name: 'd', version: '1.0.0');
        $c = ModuleFactory::make(name: 'c', version: '1.0.0', dependencies: ['d' => '*']);
        $b = ModuleFactory::make(name: 'b', version: '1.0.0', dependencies: ['c' => '*']);
        $a = ModuleFactory::make(name: 'a', version: '1.0.0', dependencies: ['b' => '*']);

        $sorted = (new TopologicalSorter())->sort([$a, $b, $c, $d]);
        $names = array_map(static fn($module): string => $module->name, $sorted);

        self::assertSame(['d', 'c', 'b', 'a'], $names);
    }
}
