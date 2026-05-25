<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\UseCases\EnableModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyEnabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyDisabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyMissingException;
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
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnableModuleUseCaseTest extends TestCase
{
    use CreatesModuleFiles;
    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/enable_test_' . uniqid();
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
    public function enablesDisabledModule(): void
    {
        $this->createModule('blog', enabled: false);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertTrue($result->isEnabled());
        $this->assertNotNull($result->state->updatedAt);

        $state = $this->readState('blog');
        $this->assertTrue($state['enabled']);
    }

    #[Test]
    public function throwsWhenAlreadyEnabled(): void
    {
        $this->createModule('blog', enabled: true);
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleAlreadyEnabledException::class);
        $useCase->execute('blog');
    }

    #[Test]
    public function throwsWhenDependencyMissing(): void
    {
        $this->createModule('blog', enabled: false, dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleDependencyMissingException::class);
        $useCase->execute('blog');
    }

    #[Test]
    public function throwsWhenDependencyDisabled(): void
    {
        $this->createModule('users', enabled: false);
        $this->createModule('blog', enabled: false, dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleDependencyDisabledException::class);
        $useCase->execute('blog');
    }

    #[Test]
    public function enableSucceedsWithSatisfiedDependency(): void
    {
        $this->createModule('users', enabled: true);
        $this->createModule('blog', enabled: false, dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertTrue($result->isEnabled());
    }

    #[Test]
    public function preservesInstalledAtTimestamp(): void
    {
        $installedAt = '2026-01-01T00:00:00+00:00';
        $this->createModule('blog', enabled: false, installedAt: $installedAt);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertSame($installedAt, $result->state->installedAt);
    }

    #[Test]
    public function invalidatesCacheAfterEnable(): void
    {
        $this->createModule('blog', enabled: false);
        $cachePath = $this->tempDir . '/bootstrap/cache/modules.php';

        [$useCase, $registry] = $this->makeUseCaseWithRegistry();
        $registry->all();

        file_put_contents($cachePath, '<?php return [];');
        $this->assertFileExists($cachePath);

        $useCase->execute('blog');

        $this->assertFileDoesNotExist($cachePath);
    }

    #[Test]
    public function doesNotModifyModuleJson(): void
    {
        $this->createModule('blog', enabled: false);
        $manifestBefore = file_get_contents($this->tempDir . '/app/Modules/Blog/module.json');

        $this->makeUseCase()->execute('blog');

        $manifestAfter = file_get_contents($this->tempDir . '/app/Modules/Blog/module.json');
        $this->assertSame($manifestBefore, $manifestAfter);
    }

    /**
     * @return array{0: EnableModuleUseCase, 1: ModuleRegistry}
     */
    private function makeUseCaseWithRegistry(): array
    {
        $stateRepo = $this->stateRepository();
        $registry = $this->registry($stateRepo);
        $sorter = new TopologicalSorter();
        $guard = new ModuleDependencyGuard($registry, $sorter);
        $cache = $this->cacheInstance($stateRepo);
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        return [new EnableModuleUseCase($registry, $stateRepo, $guard, $invalidator), $registry];
    }

    private function makeUseCase(): EnableModuleUseCase
    {
        $stateRepo = $this->stateRepository();
        $registry = $this->registry($stateRepo);
        $sorter = new TopologicalSorter();
        $guard = new ModuleDependencyGuard($registry, $sorter);
        $cache = $this->cacheInstance($stateRepo);
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        return new EnableModuleUseCase($registry, $stateRepo, $guard, $invalidator);
    }

    private function stateRepository(): ModuleStateRepository
    {
        return new ModuleStateRepository(
            paths: new ModuleStatePaths(
                config: $this->config(),
                basePath: $this->tempDir,
            ),
            writer: new AtomicJsonWriter(),
        );
    }

    private function config(): Repository
    {
        return new Repository([
            'modules' => [
                'paths' => [
                    'directories' => ['app/Modules'],
                    'state' => $this->stateRoot,
                ],
            ],
        ]);
    }

    private function cacheInstance(ModuleStateRepository $stateRepo): ModuleRegistryCache
    {
        return new ModuleRegistryCache(
            validator: new ManifestValidator(new ManifestSettingsValidator()),
            layout: new ModuleLayout(),
            stateRepository: $stateRepo,
            basePath: $this->tempDir,
        );
    }

    private function registry(ModuleStateRepository $stateRepo): ModuleRegistry
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = $this->config();

        return new ModuleRegistry(
            manifests: new ModuleManifestRepository(
                layout: $layout,
                writer: new AtomicJsonWriter(),
                validator: $validator,
                namespaceResolver: new FakeNamespaceResolver($this->tempDir),
                documentReader: new ManifestDocumentReader(),
                stateRepository: $stateRepo,
            ),
            sorter: new TopologicalSorter(),
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new Filesystem(),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            cache: $this->cacheInstance($stateRepo),
        );
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function createModule(
        string $name,
        bool $enabled = true,
        array $dependencies = [],
        ?string $installedAt = null,
    ): void {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, dependencies: $dependencies);
        $this->writeModuleState($this->stateRoot, $name, $enabled, $installedAt, new \stdClass());
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(string $moduleName): array
    {
        return $this->readStateFile($this->stateRoot, $moduleName);
    }
}
