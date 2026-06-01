<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Registry;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Registry\VO\ModuleRegistrySnapshot;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleRegistrySnapshot::class)]
#[Group('registry')]
final class ModuleRegistrySnapshotTest extends TestCase
{
    #[Test]
    public function buildsMapFromModules(): void
    {
        $users = ModuleFactory::make(name: 'users');
        $blog = ModuleFactory::make(name: 'blog');

        $snapshot = new ModuleRegistrySnapshot([$users, $blog]);

        self::assertSame(2, $snapshot->count());
        self::assertSame([$users, $blog], $snapshot->all());
    }

    #[Test]
    public function allReturnsDeterministicOrder(): void
    {
        $a = ModuleFactory::make(name: 'alpha');
        $b = ModuleFactory::make(name: 'beta');
        $c = ModuleFactory::make(name: 'gamma');

        $snapshot = new ModuleRegistrySnapshot([$b, $a, $c]);

        self::assertSame(['beta', 'alpha', 'gamma'], array_map(
            static fn ($m): string => $m->name,
            $snapshot->all(),
        ));
    }

    #[Test]
    public function findReturnsModuleByName(): void
    {
        $blog = ModuleFactory::make(name: 'blog');
        $snapshot = new ModuleRegistrySnapshot([$blog]);

        self::assertSame($blog, $snapshot->find('blog'));
    }

    #[Test]
    public function findThrowsModuleNotFoundException(): void
    {
        $snapshot = new ModuleRegistrySnapshot([]);

        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('Module [missing] was not found');

        $snapshot->find('missing');
    }

    #[Test]
    public function hasReturnsTrueForExistingModule(): void
    {
        $blog = ModuleFactory::make(name: 'blog');
        $snapshot = new ModuleRegistrySnapshot([$blog]);

        self::assertTrue($snapshot->has('blog'));
        self::assertFalse($snapshot->has('missing'));
    }

    #[Test]
    public function duplicateModuleNamesThrowInvalidManifestException(): void
    {
        $blog1 = ModuleFactory::make(name: 'blog');
        $blog2 = ModuleFactory::make(name: 'blog');

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('duplicate module name [blog]');

        new ModuleRegistrySnapshot([$blog1, $blog2]);
    }

    #[Test]
    public function emptySnapshotHasZeroCount(): void
    {
        $snapshot = new ModuleRegistrySnapshot([]);

        self::assertSame(0, $snapshot->count());
        self::assertSame([], $snapshot->all());
    }
}
