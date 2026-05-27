<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestMetaTest extends TestCase
{
    #[Test]
    public function it_accepts_valid_snake_case_names(): void
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
    public function it_rejects_non_snake_case_names(string $name): void
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
    public function it_rejects_camel_case_display_name_key(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta contains unknown key [displayName]');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'displayName' => 'Blog'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_uses_display_name_snake_case_key(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'display_name' => 'My Blog'],
            '/tmp/module.json',
        );

        self::assertSame('My Blog', $meta->displayName);
    }

    #[Test]
    public function it_falls_back_to_name_when_display_name_is_absent(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        self::assertSame('blog', $meta->displayName);
    }

    #[Test]
    public function it_rejects_missing_version_field(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.version must be a non-empty string');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_parses_kind_into_module_kind_enum(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'integration', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        self::assertSame(ModuleKind::Integration, $meta->kind);
    }

    #[Test]
    public function it_rejects_unknown_kind_value(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.kind [plugin] is not valid');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'plugin', 'version' => '1.0.0'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_rejects_missing_kind(): void
    {
        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('meta.kind must be a non-empty string');

        ManifestMeta::fromArray(
            ['name' => 'blog', 'version' => '1.0.0'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function kind_survives_round_trip(): void
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
    public function it_parses_group_when_present(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => 'content'],
            '/tmp/module.json',
        );

        self::assertSame('content', $meta->group);
    }

    #[Test]
    public function it_allows_null_group_when_absent(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        self::assertNull($meta->group);
    }

    #[Test]
    #[DataProvider('invalidGroupProvider')]
    public function it_rejects_invalid_group_format(string $group): void
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
            'starts with digit' => ['1content'],
            'empty string' => [''],
        ];
    }

    #[Test]
    public function to_array_includes_group_when_non_null(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => 'content'],
            '/tmp/module.json',
        );

        $array = $meta->toArray();

        self::assertSame('content', $array['group']);
    }

    #[Test]
    public function to_array_omits_group_when_null(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        $array = $meta->toArray();

        self::assertArrayNotHasKey('group', $array);
    }

    #[Test]
    public function group_survives_round_trip(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => 'content'],
            '/tmp/module.json',
        );

        $restored = ManifestMeta::fromArray($meta->toArray(), '/tmp/module.json');

        self::assertSame('content', $restored->group);
    }

    #[Test]
    public function group_accepts_valid_kebab_case_values(): void
    {
        foreach (['content', 'e-commerce', 'core-services', 'a', 'billing2'] as $group) {
            $meta = ManifestMeta::fromArray(
                ['name' => 'blog', 'kind' => 'module', 'version' => '1.0.0', 'group' => $group],
                '/tmp/module.json',
            );

            self::assertSame($group, $meta->group);
        }
    }
}
