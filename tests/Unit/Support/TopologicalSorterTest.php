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
}
