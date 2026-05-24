<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
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
                ['name' => $name, 'version' => '1.0.0'],
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
            ['name' => $name, 'version' => '1.0.0'],
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
            ['name' => 'blog', 'version' => '1.0.0', 'displayName' => 'Blog'],
            '/tmp/module.json',
        );
    }

    #[Test]
    public function it_uses_display_name_snake_case_key(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'version' => '1.0.0', 'display_name' => 'My Blog'],
            '/tmp/module.json',
        );

        self::assertSame('My Blog', $meta->displayName);
    }

    #[Test]
    public function it_falls_back_to_name_when_display_name_is_absent(): void
    {
        $meta = ManifestMeta::fromArray(
            ['name' => 'blog', 'version' => '1.0.0'],
            '/tmp/module.json',
        );

        self::assertSame('blog', $meta->displayName);
    }
}
