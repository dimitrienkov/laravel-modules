<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\DTOs\ScaffoldModuleConfig;
use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleLifecyclePaths;
use DimitrienkoV\LaravelModules\Application\UseCases\ScaffoldModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyExistsException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleScaffoldException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Manifest\ModuleStateRepository;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\ModuleStatePaths;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScaffoldModuleUseCaseTest extends TestCase
{
    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/scaffold_test_' . uniqid();
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        $this->filesystem->makeDirectory($this->tempDir . '/bootstrap/cache', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/app/Modules', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function scaffoldsNewModule(): void
    {
        $useCase = $this->makeUseCase();

        $result = $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        $this->assertSame('blog', $result->name);
        $this->assertTrue($result->enabled);
        $this->assertDirectoryExists($result->path);
        $this->assertFileExists($result->path . '/module.json');
        $this->assertFileExists($this->stateRoot . '/blog/state.json');
        $this->assertDirectoryExists($result->path . '/Providers');
        $this->assertDirectoryExists($result->path . '/Config');
        $this->assertDirectoryExists($result->path . '/Routes');
    }

    #[Test]
    public function scaffoldCreatesProviderStub(): void
    {
        $useCase = $this->makeUseCase();

        $result = $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        $providerFile = $result->path . '/Providers/BlogServiceProvider.php';
        $this->assertFileExists($providerFile);
        $content = file_get_contents($providerFile);
        $this->assertStringContainsString('declare(strict_types=1)', $content);
        $this->assertStringContainsString('BlogServiceProvider', $content);
    }

    #[Test]
    public function scaffoldDisabledModule(): void
    {
        $useCase = $this->makeUseCase();

        $result = $useCase->execute(new ScaffoldModuleConfig(name: 'blog', disabled: true));

        $this->assertFalse($result->enabled);
        $state = json_decode(file_get_contents($this->stateRoot . '/blog/state.json'), true);
        $this->assertFalse($state['enabled']);
    }

    #[Test]
    public function scaffoldThrowsOnInvalidName(): void
    {
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleScaffoldException::class);
        $this->expectExceptionMessageMatches('/invalid module name/');

        $useCase->execute(new ScaffoldModuleConfig(name: 'Invalid-Name!'));
    }

    #[Test]
    public function scaffoldThrowsWhenModuleExists(): void
    {
        $useCase = $this->makeUseCase();
        $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        $this->expectException(ModuleAlreadyExistsException::class);

        $useCase2 = $this->makeUseCase();
        $useCase2->execute(new ScaffoldModuleConfig(name: 'blog'));
    }

    #[Test]
    public function scaffoldForceOverwritesExisting(): void
    {
        $useCase = $this->makeUseCase();
        $useCase->execute(new ScaffoldModuleConfig(name: 'blog'));

        $useCase2 = $this->makeUseCase();
        $result = $useCase2->execute(new ScaffoldModuleConfig(name: 'blog', force: true));

        $this->assertSame('blog', $result->name);
        $this->assertFileExists($result->path . '/module.json');
    }

    #[Test]
    public function scaffoldWritesValidManifestAndState(): void
    {
        $useCase = $this->makeUseCase();

        $result = $useCase->execute(new ScaffoldModuleConfig(name: 'user_auth'));

        $manifest = json_decode(file_get_contents($result->path . '/module.json'), true);
        $this->assertSame('user_auth', $manifest['meta']['name']);
        $this->assertSame('1.0.0', $manifest['meta']['version']);
        $this->assertArrayNotHasKey('state', $manifest);

        $state = json_decode(file_get_contents($this->stateRoot . '/user_auth/state.json'), true);
        $this->assertNotNull($state['installed_at']);
    }

    private function makeUseCase(): ScaffoldModuleUseCase
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules'], 'state' => $this->stateRoot]],
        ]);

        $namespaceResolver = new FakeNamespaceResolver($this->tempDir);

        $stateRepo = new ModuleStateRepository(
            paths: new ModuleStatePaths(config: $config, basePath: $this->tempDir),
            writer: new AtomicJsonWriter(),
        );

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: $namespaceResolver,
            documentReader: new ManifestDocumentReader(),
            stateRepository: $stateRepo,
        );

        $cache = new ModuleRegistryCache($validator, $layout, $stateRepo, $this->tempDir);

        $registry = new ModuleRegistry(
            manifests: $manifests,
            sorter: new TopologicalSorter(),
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new Filesystem(),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            cache: $cache,
        );

        $paths = new ModuleLifecyclePaths($config, $this->tempDir, $this->tempDir . '/app');
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        return new ScaffoldModuleUseCase(
            registry: $registry,
            manifestRepository: $manifests,
            stateRepository: $stateRepo,
            namespaceResolver: $namespaceResolver,
            paths: $paths,
            invalidator: $invalidator,
            filesystem: new Filesystem(),
        );
    }
}
