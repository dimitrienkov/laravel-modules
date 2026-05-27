<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Manifest;

use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Exceptions\InvalidManifestException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleNotFoundException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\VO\ModuleState;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Filesystem\Filesystem;
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
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

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
        self::assertSame('^1.0', $module->meta->dependencies->constraintFor('users'));
    }

    #[Test]
    public function it_loads_module_with_state_from_state_repository(): void
    {
        $this->writeManifest($this->validManifest());

        $state = new ModuleState(true, '2026-05-25T00:00:00+00:00');
        $module = $this->repository($state)->load($this->modulePath);

        self::assertTrue($module->isEnabled());
        self::assertSame('2026-05-25T00:00:00+00:00', $module->state->installedAt);
    }

    #[Test]
    public function it_loads_module_with_disabled_default_when_no_state(): void
    {
        $this->writeManifest($this->validManifest());

        $module = $this->repository()->load($this->modulePath);

        self::assertFalse($module->isEnabled());
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
    public function it_writes_immutable_manifest_without_state_or_values(): void
    {
        $this->writeManifest($this->validManifest());

        $module = ModuleFactory::make(path: $this->modulePath);
        $this->repository()->writeManifest($module);

        $stored = $this->readStoredManifest();

        self::assertArrayHasKey('meta', $stored);
        self::assertArrayHasKey('settings', $stored);
        self::assertArrayNotHasKey('state', $stored);
        self::assertArrayNotHasKey('values', $stored['settings']);
    }

    #[Test]
    public function it_accepts_empty_settings_object(): void
    {
        $manifest = $this->validManifest();
        $manifest['settings'] = ['schema' => []];
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
    public function it_rejects_manifest_with_state_section(): void
    {
        $manifest = $this->validManifest();
        $manifest['state'] = ['enabled' => true];
        $this->writeManifest($manifest);

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('unknown top-level key [state]');

        $this->repository()->load($this->modulePath);
    }

    #[Test]
    public function it_rejects_manifest_with_settings_values(): void
    {
        $manifest = $this->validManifest();
        $manifest['settings']['values'] = [];
        $this->writeManifest($manifest);

        $this->expectException(InvalidManifestException::class);
        $this->expectExceptionMessage('settings contains unknown key [values]');

        $this->repository()->load($this->modulePath);
    }

    private function repository(?ModuleState $state = null): ModuleManifestRepository
    {
        $stateRepo = $this->createMock(ModuleStateRepositoryInterface::class);
        $stateRepo->method('readState')->willReturn($state ?? ModuleState::defaultDisabled());

        return new ModuleManifestRepository(
            layout: new ModuleLayout(),
            writer: new AtomicJsonWriter(),
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
            documentReader: new ManifestDocumentReader(),
            stateRepository: $stateRepo,
            filesystem: new LocalFilesystem(new Filesystem()),
        );
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
                'dependencies' => ['users' => '^1.0'],
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
            ],
        ];
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

}
