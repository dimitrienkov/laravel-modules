<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleDependencies::class)]
#[Group('manifest')]
final class ModuleDependenciesTest extends TestCase
{
    #[Test]
    public function rejectsListFormDependencies(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('list form is not supported');

        ModuleDependencies::fromArray(['users', 'catalog_product'], '/tmp/module.json');
    }

    #[Test]
    public function acceptsValidSnakeCaseDependencyNamesInObjectForm(): void
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
    public function rejectsNonSnakeCaseDependencyNamesInObjectForm(string $name): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('must be lowercase snake_case');

        ModuleDependencies::fromArray([$name => '^1.0'], '/tmp/module.json');
    }

    #[Test]
    public function rejectsEmptyConstraint(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('non-empty Composer constraint');

        ModuleDependencies::fromArray(['users' => ''], '/tmp/module.json');
    }

    #[Test]
    public function acceptsEmptyDependenciesArray(): void
    {
        $deps = ModuleDependencies::fromArray([], '/tmp/module.json');

        self::assertTrue($deps->isEmpty());
        self::assertSame([], $deps->all());
        self::assertSame([], $deps->names());
    }

    #[Test]
    public function namesReturnsDependencyNamesInSortedOrder(): void
    {
        $deps = ModuleDependencies::fromArray(
            ['users' => '^1.0', 'auth' => '*', 'media' => '>=2.0'],
            '/tmp/module.json',
        );

        self::assertSame(['auth', 'media', 'users'], $deps->names());
    }

    #[Test]
    public function constraintForReturnsNullForNonExistentDependency(): void
    {
        $deps = ModuleDependencies::fromArray(['users' => '^1.0'], '/tmp/module.json');

        self::assertNull($deps->constraintFor('nonexistent'));
    }

    #[Test]
    public function acceptsWildcardConstraintInObjectForm(): void
    {
        $deps = ModuleDependencies::fromArray(['users' => '*'], '/tmp/module.json');

        self::assertSame('*', $deps->constraintFor('users'));
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
