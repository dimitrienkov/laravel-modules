<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleDependenciesTest extends TestCase
{
    #[Test]
    public function it_accepts_valid_snake_case_dependency_names_in_list_form(): void
    {
        $deps = ModuleDependencies::fromArray(['users', 'catalog_product'], '/tmp/module.json');

        self::assertSame(['catalog_product' => '*', 'users' => '*'], $deps->all());
    }

    #[Test]
    public function it_accepts_valid_snake_case_dependency_names_in_object_form(): void
    {
        $deps = ModuleDependencies::fromArray(
            ['users' => '^1.0', 'catalog_product' => '>=2.0'],
            '/tmp/module.json',
        );

        self::assertSame('^1.0', $deps->constraintFor('users'));
        self::assertSame('>=2.0', $deps->constraintFor('catalog_product'));
    }

    #[Test]
    #[DataProvider('invalidDependencyNameProvider')]
    public function it_rejects_non_snake_case_dependency_names_in_list_form(string $name): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be lowercase snake_case');

        ModuleDependencies::fromArray([$name], '/tmp/module.json');
    }

    #[Test]
    #[DataProvider('invalidDependencyNameProvider')]
    public function it_rejects_non_snake_case_dependency_names_in_object_form(string $name): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be lowercase snake_case');

        ModuleDependencies::fromArray([$name => '^1.0'], '/tmp/module.json');
    }

    #[Test]
    public function it_accepts_empty_dependencies_array(): void
    {
        $deps = ModuleDependencies::fromArray([], '/tmp/module.json');

        self::assertTrue($deps->isEmpty());
        self::assertSame([], $deps->all());
        self::assertSame([], $deps->names());
    }

    #[Test]
    public function names_returns_dependency_names_in_sorted_order(): void
    {
        $deps = ModuleDependencies::fromArray(
            ['users' => '^1.0', 'auth' => '*', 'media' => '>=2.0'],
            '/tmp/module.json',
        );

        self::assertSame(['auth', 'media', 'users'], $deps->names());
    }

    #[Test]
    public function constraint_for_returns_null_for_non_existent_dependency(): void
    {
        $deps = ModuleDependencies::fromArray(['users' => '^1.0'], '/tmp/module.json');

        self::assertNull($deps->constraintFor('nonexistent'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDependencyNameProvider(): array
    {
        return [
            'PascalCase' => ['CatalogProduct'],
            'kebab-case' => ['catalog-product'],
            'starts with digit' => ['123users'],
        ];
    }
}
