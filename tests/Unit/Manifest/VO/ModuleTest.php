<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleTest extends TestCase
{
    #[Test]
    public function from_manifest_creates_module_from_valid_manifest(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
        );

        self::assertSame('blog', $module->name);
        self::assertSame('Blog', $module->displayName);
        self::assertSame('App\\Modules\\Blog', $module->namespace);
        self::assertSame('/app/Modules/Blog', $module->path);
        self::assertSame('1.0.0', $module->meta->version);
    }

    #[Test]
    public function is_enabled_returns_true_when_state_enabled_is_true(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(enabled: true),
            '/app/Modules/Blog/module.json',
        );

        self::assertTrue($module->isEnabled());
    }

    #[Test]
    public function is_enabled_returns_false_when_state_enabled_is_false(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(enabled: false),
            '/app/Modules/Blog/module.json',
        );

        self::assertFalse($module->isEnabled());
    }

    #[Test]
    public function manifest_path_returns_path_with_module_json(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
        );

        self::assertSame('/app/Modules/Blog/module.json', $module->manifestPath());
    }

    #[Test]
    public function with_state_returns_new_module_with_updated_state(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(enabled: true),
            '/app/Modules/Blog/module.json',
        );

        $newState = new ManifestState(false, '2026-05-24T00:00:00+00:00');
        $updated = $module->withState($newState);

        self::assertTrue($module->isEnabled());
        self::assertFalse($updated->isEnabled());
        self::assertSame('blog', $updated->name);
    }

    #[Test]
    public function to_descriptor_array_returns_manifest_without_values(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
        );

        $descriptor = $module->toDescriptorArray();

        self::assertArrayHasKey('meta', $descriptor);
        self::assertArrayHasKey('state', $descriptor);
        self::assertArrayHasKey('settings', $descriptor);
        self::assertArrayHasKey('schema', $descriptor['settings']);
        self::assertArrayNotHasKey('values', $descriptor['settings']);
    }

    #[Test]
    public function to_manifest_array_includes_values(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
        );

        $values = FeatureValues::fromArray(
            ['comments_enabled' => false],
            $module->features,
            'blog',
            '/app/Modules/Blog/module.json',
        );

        $manifest = $module->toManifestArray($values);

        self::assertArrayHasKey('values', $manifest['settings']);
        self::assertSame(['comments_enabled' => false], $manifest['settings']['values']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validManifest(bool $enabled = true): array
    {
        return [
            'meta' => [
                'name' => 'blog',
                'display_name' => 'Blog',
                'version' => '1.0.0',
                'dependencies' => [],
            ],
            'state' => [
                'enabled' => $enabled,
            ],
            'settings' => [
                'schema' => [
                    'comments_enabled' => ['type' => 'bool', 'default' => true],
                ],
                'values' => [],
            ],
        ];
    }
}
