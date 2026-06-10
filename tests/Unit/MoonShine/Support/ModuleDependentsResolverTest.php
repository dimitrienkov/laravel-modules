<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine\Support;

use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleDependentsResolver;
use DimitrienkoV\LaravelModules\Tests\Support\FakeModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleDependentsResolver::class)]
#[Group('moonshine')]
final class ModuleDependentsResolverTest extends TestCase
{
    #[Test]
    public function disableBlockersCountOnlyEnabledDependents(): void
    {
        $resolver = new ModuleDependentsResolver($this->registry());

        // blog (enabled) and shop (disabled) both depend on users.
        self::assertSame(['Blog'], $resolver->disableBlockers('users'));
    }

    #[Test]
    public function removeBlockersCountAnyDependentRegardlessOfState(): void
    {
        $resolver = new ModuleDependentsResolver($this->registry());

        self::assertSame(['Blog', 'Shop'], $resolver->removeBlockers('users'));
    }

    #[Test]
    public function returnsEmptyWhenNoModuleDependsOnTheTarget(): void
    {
        $resolver = new ModuleDependentsResolver($this->registry());

        self::assertSame([], $resolver->disableBlockers('media'));
        self::assertSame([], $resolver->removeBlockers('media'));
    }

    #[Test]
    public function ordersDependentsDeterministicallyByDisplayName(): void
    {
        $registry = new FakeModuleRegistry();
        // Insert out of alphabetical order to prove the resolver sorts.
        $registry->add(ModuleFactory::make(name: 'users'));
        $registry->add(ModuleFactory::make(name: 'shop', dependencies: ['users' => '^1.0']));
        $registry->add(ModuleFactory::make(name: 'analytics', dependencies: ['users' => '^1.0']));
        $registry->add(ModuleFactory::make(name: 'blog', dependencies: ['users' => '^1.0']));

        $resolver = new ModuleDependentsResolver($registry);

        self::assertSame(['Analytics', 'Blog', 'Shop'], $resolver->removeBlockers('users'));
    }

    private function registry(): FakeModuleRegistry
    {
        $registry = new FakeModuleRegistry();
        $registry->add(ModuleFactory::make(name: 'users'));
        $registry->add(ModuleFactory::make(name: 'media'));
        $registry->add(ModuleFactory::make(name: 'blog', enabled: true, dependencies: ['users' => '^1.5']));
        $registry->add(ModuleFactory::make(name: 'shop', enabled: false, dependencies: ['users' => '>=1.0']));

        return $registry;
    }
}
