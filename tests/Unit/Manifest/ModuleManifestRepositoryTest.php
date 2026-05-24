<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\ManifestWriteException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\FeatureValues;
use DimitrienkoV\LaravelModules\Manifest\ManifestState;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ComposerNamespaceResolver;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleManifestRepositoryTest extends TestCase
{
    private string $modulePath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-repository-' . bin2hex(random_bytes(6));
        $this->modulePath = $this->tempDir . '/app/Modules/Blog';

        mkdir($this->modulePath, 0755, true);
        $this->writeComposer();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_loads_valid_manifest_into_module_value_object(): void
    {
        $this->writeManifest($this->validManifest());

        $module = $this->repository()->load($this->modulePath);

        self::assertSame('blog', $module->name);
        self::assertSame('Blog', $module->displayName);
        self::assertSame('App\\Modules\\Blog', $module->namespace);
        self::assertTrue($module->isEnabled());
        self::assertSame('*', $module->meta->dependencies->constraintFor('users'));
        self::assertSame(true, $module->values->get('blog', 'comments_enabled'));
        self::assertSame(20, $module->values->get('blog', 'posts_per_page'));
    }

    #[Test]
    public function it_throws_for_missing_manifest(): void
    {
        $this->expectException(ModuleNotFoundException::class);
        $this->expectExceptionMessage('module.json manifest');

        $this->repository()->load($this->modulePath);
    }

    #[Test]
    public function it_throws_for_invalid_json(): void
    {
        file_put_contents($this->modulePath . '/module.json', '{');

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('Syntax error');

        $this->repository()->load($this->modulePath);
    }

    #[Test]
    public function it_saves_canonical_manifest_without_persisting_feature_defaults(): void
    {
        $this->writeManifest($this->validManifest());
        $module = $this->repository()->load($this->modulePath);

        $this->repository()->save($module);

        $stored = $this->readStoredManifest();

        self::assertSame(['posts_per_page' => 20], $stored['settings']['values']);
        self::assertSame(['users' => '*'], $stored['meta']['dependencies']);
        self::assertFileExists($this->modulePath . '/module.json.lock');
    }

    #[Test]
    public function it_updates_state_through_typed_value_object(): void
    {
        $this->writeManifest($this->validManifest());
        $module = $this->repository()->load($this->modulePath);

        $updated = $this->repository()->updateState($module, new ManifestState(false, '2026-05-24T00:00:00+00:00'));
        $stored = $this->readStoredManifest();

        self::assertFalse($updated->isEnabled());
        self::assertFalse($stored['state']['enabled']);
        self::assertSame('2026-05-24T00:00:00+00:00', $stored['state']['installed_at']);
    }

    #[Test]
    public function it_updates_feature_values_through_typed_value_object(): void
    {
        $this->writeManifest($this->validManifest());
        $module = $this->repository()->load($this->modulePath);
        $values = FeatureValues::fromArray(
            ['comments_enabled' => false, 'posts_per_page' => 30],
            $module->features,
            $module->manifestPath(),
        );

        $updated = $this->repository()->updateFeatureValues($module, $values);
        $stored = $this->readStoredManifest();

        self::assertFalse($updated->values->get('blog', 'comments_enabled'));
        self::assertSame(30, $stored['settings']['values']['posts_per_page']);
    }

    #[Test]
    public function feature_values_are_validated_before_update(): void
    {
        $this->writeManifest($this->validManifest());
        $module = $this->repository()->load($this->modulePath);

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('less than or equal to 50');

        FeatureValues::fromArray(['posts_per_page' => 100], $module->features, $module->manifestPath());
    }

    #[Test]
    public function it_accepts_empty_settings_object(): void
    {
        $manifest = $this->validManifest();
        $manifest['settings'] = [
            'schema' => [],
            'values' => [],
        ];
        $this->writeManifest($manifest);

        $module = $this->repository()->load($this->modulePath);

        self::assertSame('blog', $module->name);
    }

    #[Test]
    public function it_accepts_feature_definitions_with_ui_metadata(): void
    {
        $manifest = $this->validManifest();
        $manifest['settings']['schema']['comments_enabled']['label'] = 'Включить комментарии';
        $manifest['settings']['schema']['comments_enabled']['description'] = 'Когда включено, пользователи могут оставлять комментарии';
        $manifest['settings']['schema']['comments_enabled']['group'] = 'Фичи';
        $this->writeManifest($manifest);

        $module = $this->repository()->load($this->modulePath);
        $definition = $module->features->all()['comments_enabled'];

        self::assertSame('Включить комментарии', $definition->label);
        self::assertSame('Когда включено, пользователи могут оставлять комментарии', $definition->description);
        self::assertSame('Фичи', $definition->group);

        $serialized = $definition->toArray();
        self::assertSame('Включить комментарии', $serialized['label']);
        self::assertSame('Фичи', $serialized['group']);
    }

    #[Test]
    public function it_accepts_license_in_meta(): void
    {
        $manifest = $this->validManifest();
        $manifest['meta']['license'] = 'proprietary';
        $this->writeManifest($manifest);

        $module = $this->repository()->load($this->modulePath);

        self::assertSame('proprietary', $module->meta->license);
    }

    #[Test]
    public function it_accepts_updated_at_in_state(): void
    {
        $manifest = $this->validManifest();
        $manifest['state']['updated_at'] = '2026-05-24T12:00:00+00:00';
        $this->writeManifest($manifest);

        $module = $this->repository()->load($this->modulePath);

        self::assertSame('2026-05-24T12:00:00+00:00', $module->state->updatedAt);
    }

    #[Test]
    public function it_uses_atomic_writer_errors_for_failed_manifest_writes(): void
    {
        mkdir($this->modulePath . '/module.json');

        $this->expectException(ManifestWriteException::class);
        $this->expectExceptionMessage('temporary file could not be renamed atomically');

        $this->repository()->save(ModuleFactory::make(path: $this->modulePath));
    }

    private function repository(): ModuleManifestRepository
    {
        return new ModuleManifestRepository(
            layout: new ModuleLayout(),
            writer: new AtomicJsonWriter(),
            validator: new ManifestValidator(),
            namespaceResolver: new ComposerNamespaceResolver($this->tempDir),
        );
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
                'dependencies' => ['users'],
            ],
            'state' => [
                'enabled' => true,
            ],
            'settings' => [
                'schema' => [
                    'comments_enabled' => [
                        'type' => 'bool',
                        'default' => true,
                    ],
                    'posts_per_page' => [
                        'type' => 'int',
                        'default' => 10,
                        'min' => 1,
                        'max' => 50,
                    ],
                ],
                'values' => [
                    'posts_per_page' => 20,
                ],
            ],
        ];
    }

    private function writeComposer(): void
    {
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode([
                'autoload' => [
                    'psr-4' => [
                        'App\\' => 'app/',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeManifest(array $manifest): void
    {
        file_put_contents(
            $this->modulePath . '/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readStoredManifest(): array
    {
        /** @var array<string, mixed> */
        return json_decode(
            (string) file_get_contents($this->modulePath . '/module.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
