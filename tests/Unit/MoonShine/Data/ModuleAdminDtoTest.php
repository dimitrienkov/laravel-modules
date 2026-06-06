<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\MoonShine\Data;

use DimitrienkoV\LaravelModules\Manifest\Enums\ModuleKind;
use DimitrienkoV\LaravelModules\Manifest\VO\Checksum;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureSchema;
use DimitrienkoV\LaravelModules\Manifest\VO\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\VO\ManifestMeta;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleDependencies;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleGroup;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleOrigin;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Manifest\VO\Version;
use DimitrienkoV\LaravelModules\MoonShine\Data\ModuleAdminDto;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleAdminDto::class)]
#[Group('moonshine')]
final class ModuleAdminDtoTest extends TestCase
{
    private const string MANIFEST_PATH = '/var/www/app/Modules/Blog/module.json';

    #[Test]
    public function buildsFromModuleWithEveryColumn(): void
    {
        $module = $this->moduleWithSchema();
        $values = FeatureValues::fromArray(['max_posts' => 50], $module->features, 'blog', self::MANIFEST_PATH);
        $source = ModuleOrigin::forZip(new Version('1.2.3'), new Checksum(str_repeat('a', 64)));

        $dto = ModuleAdminDto::fromModule($module, $values, $source, 4);

        self::assertSame('blog', $dto->name);
        self::assertSame('Blog', $dto->displayName);
        self::assertSame('1.2.3', $dto->version);
        self::assertSame('module', $dto->kind);
        self::assertSame('content', $dto->group);
        self::assertTrue($dto->enabled);
        self::assertSame('App\\Modules\\Blog', $dto->namespace);
        self::assertSame('/var/www/app/Modules/Blog', $dto->path);
        self::assertSame(4, $dto->loadOrder);
        self::assertSame(['users' => '^1.5'], $dto->dependencies);
        self::assertSame('zip', $dto->provenanceKind);
        self::assertSame('1.2.3', $dto->provenanceVersion);
        self::assertSame(str_repeat('a', 64), $dto->provenanceChecksum);
    }

    #[Test]
    public function effectiveValuesMergeOverridesWithSchemaDefaults(): void
    {
        $module = $this->moduleWithSchema();
        $values = FeatureValues::fromArray(['max_posts' => 50], $module->features, 'blog', self::MANIFEST_PATH);

        $dto = ModuleAdminDto::fromModule($module, $values, null, 0);

        // enable_comments -> default, max_posts -> explicit override, mode -> default.
        self::assertSame(
            [
                'enable_comments' => true,
                'max_posts' => 50,
                'mode' => 'auto',
            ],
            $dto->featureValues,
        );
    }

    #[Test]
    public function toArrayCoversEveryKey(): void
    {
        $module = $this->moduleWithSchema();
        $values = FeatureValues::fromArray([], $module->features, 'blog', self::MANIFEST_PATH);

        $dto = ModuleAdminDto::fromModule($module, $values, ModuleOrigin::forLocal(new Version('1.2.3')), 2);

        self::assertSame(
            [
                'name',
                'displayName',
                'version',
                'kind',
                'group',
                'enabled',
                'namespace',
                'path',
                'loadOrder',
                'dependencies',
                'featureValues',
                'provenanceKind',
                'provenanceVersion',
                'provenanceChecksum',
            ],
            array_keys($dto->toArray()),
        );

        // local provenance carries no checksum.
        self::assertSame('local', $dto->toArray()['provenanceKind']);
        self::assertNull($dto->toArray()['provenanceChecksum']);
    }

    #[Test]
    public function handlesModuleWithoutSchemaOrProvenance(): void
    {
        $module = ModuleFactory::make(name: 'core', namespace: 'App\\Modules\\Core');
        $values = FeatureValues::fromArray([], $module->features, 'core', self::MANIFEST_PATH);

        $dto = ModuleAdminDto::fromModule($module, $values, null, 0);

        self::assertSame([], $dto->featureValues);
        self::assertNull($dto->group);
        self::assertNull($dto->provenanceKind);
        self::assertNull($dto->provenanceVersion);
        self::assertNull($dto->provenanceChecksum);
        self::assertSame([], $dto->dependencies);
    }

    private function moduleWithSchema(): Module
    {
        $schema = FeatureSchema::fromArray([
            'enable_comments' => ['type' => 'bool', 'default' => true, 'label' => 'Enable comments'],
            'max_posts' => ['type' => 'int', 'default' => 20, 'min' => 1, 'max' => 100, 'group' => 'content'],
            'mode' => ['type' => 'enum', 'default' => 'auto', 'options' => ['auto', 'manual']],
        ], self::MANIFEST_PATH);

        $meta = new ManifestMeta(
            name: 'blog',
            displayName: 'Blog',
            kind: ModuleKind::Module,
            version: new Version('1.2.3'),
            author: 'Acme',
            description: 'Corporate blog',
            license: 'proprietary',
            dependencies: ModuleDependencies::fromArray(['users' => '^1.5'], self::MANIFEST_PATH),
            group: new ModuleGroup('content'),
        );

        return new Module(
            name: 'blog',
            displayName: 'Blog',
            namespace: 'App\\Modules\\Blog',
            path: '/var/www/app/Modules/Blog',
            schemaVersion: 1,
            meta: $meta,
            state: new ModuleState(enabled: true, installedAt: '2026-01-01T00:00:00+00:00'),
            features: $schema,
        );
    }
}
