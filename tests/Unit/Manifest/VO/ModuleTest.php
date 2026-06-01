<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest\VO;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Module::class)]
#[Group('manifest')]
final class ModuleTest extends TestCase
{
    #[Test]
    public function fromManifestCreatesModuleFromValidManifest(): void
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
        self::assertSame(1, $module->schemaVersion);
        self::assertSame('1.0.0', $module->meta->version);
        self::assertSame(ModuleKind::Module, $module->meta->kind);
    }

    #[Test]
    public function isEnabledReturnsTrueWhenStateEnabledIsTrue(): void
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
    public function isEnabledReturnsFalseWhenStateEnabledIsFalse(): void
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
    public function manifestPathReturnsPathWithModuleJson(): void
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
    public function withStateReturnsNewModuleWithUpdatedState(): void
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
        self::assertSame(1, $updated->schemaVersion);
    }

    #[Test]
    public function toDescriptorArrayReturnsImmutableManifestOnly(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
            new ModuleState(true, null),
        );

        $descriptor = $module->toDescriptorArray();

        self::assertArrayHasKey('schema_version', $descriptor);
        self::assertSame(1, $descriptor['schema_version']);
        self::assertArrayHasKey('meta', $descriptor);
        self::assertArrayNotHasKey('state', $descriptor);
        self::assertArrayHasKey('settings', $descriptor);
        self::assertArrayHasKey('schema', $descriptor['settings']);
        self::assertArrayNotHasKey('values', $descriptor['settings']);
    }

    #[Test]
    public function toManifestArrayReturnsImmutableManifest(): void
    {
        $module = Module::fromManifest(
            '/app/Modules/Blog',
            'App\\Modules\\Blog',
            $this->validManifest(),
            '/app/Modules/Blog/module.json',
            new ModuleState(true, null),
        );

        $manifest = $module->toDescriptorArray();

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
            'schema_version' => 1,
            'meta' => [
                'name' => 'blog',
                'display_name' => 'Blog',
                'kind' => 'module',
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
