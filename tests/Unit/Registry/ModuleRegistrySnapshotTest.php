<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Registry;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Registry\VO\ModuleRegistrySnapshot;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleRegistrySnapshotTest extends TestCase
{
    #[Test]
    public function it_builds_map_from_modules(): void
    {
        $users = ModuleFactory::make(name: 'users');
        $blog = ModuleFactory::make(name: 'blog');

        $snapshot = new ModuleRegistrySnapshot([$users, $blog]);

        self::assertSame(2, $snapshot->count());
        self::assertSame([$users, $blog], $snapshot->all());
    }

    #[Test]
    public function all_returns_deterministic_order(): void
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
    public function find_returns_module_by_name(): void
    {
        $blog = ModuleFactory::make(name: 'blog');
        $snapshot = new ModuleRegistrySnapshot([$blog]);

        self::assertSame($blog, $snapshot->find('blog'));
    }

    #[Test]
    public function find_throws_module_not_found_exception(): void
    {
        $snapshot = new ModuleRegistrySnapshot([]);

        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('Module [missing] was not found');

        $snapshot->find('missing');
    }

    #[Test]
    public function has_returns_true_for_existing_module(): void
    {
        $blog = ModuleFactory::make(name: 'blog');
        $snapshot = new ModuleRegistrySnapshot([$blog]);

        self::assertTrue($snapshot->has('blog'));
        self::assertFalse($snapshot->has('missing'));
    }

    #[Test]
    public function duplicate_module_names_throw_invalid_manifest_exception(): void
    {
        $blog1 = ModuleFactory::make(name: 'blog');
        $blog2 = ModuleFactory::make(name: 'blog');

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('duplicate module name [blog]');

        new ModuleRegistrySnapshot([$blog1, $blog2]);
    }

    #[Test]
    public function empty_snapshot_has_zero_count(): void
    {
        $snapshot = new ModuleRegistrySnapshot([]);

        self::assertSame(0, $snapshot->count());
        self::assertSame([], $snapshot->all());
    }
}
