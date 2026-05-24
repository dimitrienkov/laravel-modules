<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Exceptions\CyclicDependencyException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyDisabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyIncompatibleException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyMissingException;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TopologicalSorterTest extends TestCase
{
    #[Test]
    public function it_sorts_modules_after_their_dependencies(): void
    {
        $users = ModuleFactory::make(name: 'users', version: '1.2.0');
        $blog = ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']);

        $sorted = (new TopologicalSorter())->sort([$blog, $users]);

        self::assertSame(['users', 'blog'], array_map(
            static fn ($module): string => $module->name,
            $sorted,
        ));
    }

    #[Test]
    public function it_fails_for_missing_dependencies(): void
    {
        $this->expectException(ModuleDependencyMissingException::class);
        $this->expectExceptionMessage('requires missing dependency [users]');

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']),
        ]);
    }

    #[Test]
    public function it_fails_for_disabled_dependencies(): void
    {
        $this->expectException(ModuleDependencyDisabledException::class);
        $this->expectExceptionMessage('requires disabled dependency [users]');

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']),
            ModuleFactory::make(name: 'users', enabled: false),
        ]);
    }

    #[Test]
    public function it_fails_for_incompatible_dependencies(): void
    {
        $this->expectException(ModuleDependencyIncompatibleException::class);
        $this->expectExceptionMessage('matching [^1.0]');

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']),
            ModuleFactory::make(name: 'users', version: '2.0.0'),
        ]);
    }

    #[Test]
    public function it_fails_for_dependency_cycles(): void
    {
        $this->expectException(CyclicDependencyException::class);
        $this->expectExceptionMessage('blog -> users -> blog');

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', dependencies: ['users' => '*']),
            ModuleFactory::make(name: 'users', dependencies: ['blog' => '*']),
        ]);
    }

    #[Test]
    public function disabled_module_with_missing_dependency_does_not_throw(): void
    {
        $sorted = (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '^1.0']),
        ]);

        self::assertSame(['blog'], array_map(
            static fn ($module): string => $module->name,
            $sorted,
        ));
    }

    #[Test]
    public function disabled_module_with_disabled_dependency_does_not_throw(): void
    {
        $sorted = (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '^1.0']),
            ModuleFactory::make(name: 'users', enabled: false, version: '1.2.0'),
        ]);

        self::assertCount(2, $sorted);
    }

    #[Test]
    public function disabled_module_with_incompatible_dependency_does_not_throw(): void
    {
        $sorted = (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '^1.0']),
            ModuleFactory::make(name: 'users', version: '2.0.0'),
        ]);

        self::assertCount(2, $sorted);
    }

    #[Test]
    public function enabled_module_with_missing_dependency_still_throws(): void
    {
        $this->expectException(ModuleDependencyMissingException::class);

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: true, dependencies: ['users' => '^1.0']),
        ]);
    }

    #[Test]
    public function disabled_modules_with_cycle_still_throws(): void
    {
        $this->expectException(CyclicDependencyException::class);

        (new TopologicalSorter())->sort([
            ModuleFactory::make(name: 'blog', enabled: false, dependencies: ['users' => '*']),
            ModuleFactory::make(name: 'users', enabled: false, dependencies: ['blog' => '*']),
        ]);
    }

    #[Test]
    public function it_handles_diamond_dependency(): void
    {
        $d = ModuleFactory::make(name: 'd', version: '1.0.0');
        $b = ModuleFactory::make(name: 'b', version: '1.0.0', dependencies: ['d' => '*']);
        $c = ModuleFactory::make(name: 'c', version: '1.0.0', dependencies: ['d' => '*']);
        $a = ModuleFactory::make(name: 'a', version: '1.0.0', dependencies: ['b' => '*', 'c' => '*']);

        $sorted = (new TopologicalSorter())->sort([$a, $b, $c, $d]);

        $names = array_map(static fn ($module): string => $module->name, $sorted);

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
    public function it_handles_three_level_deep_chain(): void
    {
        $d = ModuleFactory::make(name: 'd', version: '1.0.0');
        $c = ModuleFactory::make(name: 'c', version: '1.0.0', dependencies: ['d' => '*']);
        $b = ModuleFactory::make(name: 'b', version: '1.0.0', dependencies: ['c' => '*']);
        $a = ModuleFactory::make(name: 'a', version: '1.0.0', dependencies: ['b' => '*']);

        $sorted = (new TopologicalSorter())->sort([$a, $b, $c, $d]);
        $names = array_map(static fn ($module): string => $module->name, $sorted);

        self::assertSame(['d', 'c', 'b', 'a'], $names);
    }
}
