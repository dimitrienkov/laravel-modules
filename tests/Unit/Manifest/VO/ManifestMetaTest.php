<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManifestMeta::class)]
#[Group('manifest')]
final class ManifestMetaTest extends TestCase
{
    #[Test]
    public function acceptsValidSnakeCaseNames(): void
    {
        foreach (['blog', 'catalog_product', 'a', 'users123'] as $name) {
            $meta = ManifestMeta::fromArray(
                ['name' => $name, 'kind' => 'module', 'version' => '1.0.0'],
                '/tmp/module.json',
            );

            self::assertSame($name, $meta->name);
        }
    }

    #[Test]
    #[DataProvider('invalidNameProvider')]
    public function rejectsNonSnakeCaseNames(string $name): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.name must be lowercase snake_case');

        ManifestMeta::fromArray(
            ['name' => $name, 'kind' => 'module', 'version' => '1.0.0'],
            '/tmp/module.json',
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNameProvider(): array
    {
        return [
            'PascalCase' => ['CatalogProduct'],
            'kebab-case' => ['catalog-product'],
            'starts with digit' => ['123blog'],
            'uppercase' => ['BLOG'],
            'contains space' => ['my module'],
        ];
    }

    #[Test]
    public function rejectsCamelCaseDisplayNameKey(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta contains unknown key [displayName]');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'displayName' => 'Blog'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function usesDisplayNameSnakeCaseKey(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'display_name' => 'My Blog'],
            '/tmp/module.json',
        );

        self::assertSame('My Blog', $meta->displayName);
    }

    #[Test]
    public function fallsBackToNameWhenDisplayNameIsAbsent(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        self::assertSame('blog', $meta->displayName);
    }

    #[Test]
    public function rejectsMissingVersionField(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.version must be a non-empty string');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function parsesKindIntoModuleKindEnum(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'integration', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        self::assertSame(ModuleKind::Integration, $meta->kind);
    }

    #[Test]
    public function rejectsUnknownKindValue(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.kind [plugin] is not valid');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'plugin', 'version' => '1.0.0'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function rejectsMissingKind(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.kind must be a non-empty string');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'version' => '1.0.0'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function kindSurvivesRoundTrip(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'subsystem', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        $array = $meta->toArray();

        self::assertSame('subsystem', $array['kind']);

        $restored = ManifestMeta::fromArray($array, '/tmp/module.json');

        self::assertSame(ModuleKind::Subsystem, $restored->kind);
    }

    #[Test]
    public function parsesGroupWhenPresent(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => 'content'],
            '/tmp/module.json',
        );

        self::assertSame('content', $meta->group);
    }

    #[Test]
    public function allowsNullGroupWhenAbsent(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        self::assertNull($meta->group);
    }

    #[Test]
    #[DataProvider('invalidGroupProvider')]
    public function rejectsInvalidGroupFormat(string $group): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.group');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => $group],
            '/tmp/module.json',
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidGroupProvider(): array
    {
        return [
            'PascalCase' => ['Content'],
            'contains space' => ['my group'],
            'contains underscore' => ['my_group'],
            'trailing hyphen' => ['content-'],
            'leading hyphen' => ['-content'],
            'double hyphen' => ['foo--bar'],
            'empty string' => [''],
        ];
    }

    #[Test]
    public function toArrayIncludesGroupWhenNonNull(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => 'content'],
            '/tmp/module.json',
        );

        $array = $meta->toArray();

        self::assertSame('content', $array['group']);
    }

    #[Test]
    public function toArrayOmitsGroupWhenNull(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        $array = $meta->toArray();

        self::assertArrayNotHasKey('group', $array);
    }

    #[Test]
    public function groupSurvivesRoundTrip(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => 'content'],
            '/tmp/module.json',
        );

        $restored = ManifestMeta::fromArray($meta->toArray(), '/tmp/module.json');

        self::assertSame('content', $restored->group);
    }

    #[Test]
    public function groupAcceptsValidKebabCaseValues(): void
    {
        foreach (['content', 'e-commerce', 'core-services', 'a', 'billing2', '1content'] as $group) {
            $meta = ManifestMeta::fromArray(
                ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => $group],
                '/tmp/module.json',
            );

            self::assertSame($group, $meta->group);
        }
    }
}
