<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
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
            new ModuleState(true, null),
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
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
            new ModuleState(true, null),
        );

        self::assertTrue($module->isEnabled());
    }

    #[Test]
    public function is_enabled_returns_false_when_state_enabled_is_false(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
            new ModuleState(false, null),
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
            new ModuleState(true, null),
        );

        self::assertSame('/app/Modules/Blog/module.json', $module->manifestPath());
    }

    #[Test]
    public function with_state_returns_new_module_with_updated_state(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
            new ModuleState(true, null),
        );

        $newState = new ModuleState(false, '2026-05-24T00:00:00+00:00');
        $updated = $module->withState($newState);

        self::assertTrue($module->isEnabled());
        self::assertFalse($updated->isEnabled());
        self::assertSame('blog', $updated->name);
    }

    #[Test]
    public function to_descriptor_array_returns_immutable_manifest_only(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
            new ModuleState(true, null),
        );

        $descriptor = $module->toDescriptorArray();

        self::assertArrayHasKey('meta', $descriptor);
        self::assertArrayNotHasKey('state', $descriptor);
        self::assertArrayHasKey('settings', $descriptor);
        self::assertArrayHasKey('schema', $descriptor['settings']);
        self::assertArrayNotHasKey('values', $descriptor['settings']);
    }

    #[Test]
    public function to_manifest_array_returns_immutable_manifest(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
            new ModuleState(true, null),
        );

        $manifest = $module->toManifestArray();

        self::assertArrayHasKey('meta', $manifest);
        self::assertArrayNotHasKey('state', $manifest);
        self::assertArrayHasKey('settings', $manifest);
        self::assertArrayHasKey('schema', $manifest['settings']);
        self::assertArrayNotHasKey('values', $manifest['settings']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validManifest(): array
    {
        return [
            'meta' => [
                'name' => 'blog',
                'display_name' => 'Blog',
                'version' => '1.0.0',
                'dependencies' => [],
            ],
            'settings' => [
                'schema' => [
                    'comments_enabled' => ['type' => 'bool', 'default' => true],
                ],
            ],
        ];
    }
}
