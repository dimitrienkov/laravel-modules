<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleLifecyclePaths;
use DimitrienkoV\LaravelModules\Application\UseCases\RemoveModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\DependentModulesExistException;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoveModuleUseCaseTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/remove_test_' . uniqid();
        $this->filesystem->makeDirectory($this->tempDir . '/bootstrap/cache', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/app/Modules', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/backups', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function removesModuleWithBackup(): void
    {
        $this->createModule('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertSame('blog', $result->name);
        $this->assertNotNull($result->backupPath);
        $this->assertDirectoryExists($result->backupPath);
        $this->assertDirectoryDoesNotExist($this->tempDir . '/app/Modules/Blog');
    }

    #[Test]
    public function removesModuleWithoutBackup(): void
    {
        $this->createModule('blog');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog', noBackup: true);

        $this->assertNull($result->backupPath);
        $this->assertDirectoryDoesNotExist($this->tempDir . '/app/Modules/Blog');
    }

    #[Test]
    public function blockedByEnabledDependent(): void
    {
        $this->createModule('users');
        $this->createModule('blog', dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $this->expectException(DependentModulesExistException::class);
        $useCase->execute('users');
    }

    #[Test]
    public function blockedByDisabledDependent(): void
    {
        $this->createModule('users');
        $this->createModule('blog', enabled: false, dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $this->expectException(DependentModulesExistException::class);
        $useCase->execute('users');
    }

    #[Test]
    public function removesModuleWithNoDependents(): void
    {
        $this->createModule('blog');
        $this->createModule('users');
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertSame('blog', $result->name);
    }

    private function makeUseCase(): RemoveModuleUseCase
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules'], 'backup' => $this->tempDir . '/backups']],
        ]);

        $sorter = new TopologicalSorter();
        $cache = new ModuleRegistryCache($validator, $layout, $this->tempDir);

        $registry = new ModuleRegistry(
            manifests: new ModuleManifestRepository(
                layout: $layout,
                writer: new AtomicJsonWriter(),
                validator: $validator,
                namespaceResolver: new FakeNamespaceResolver($this->tempDir),
                documentReader: new ManifestDocumentReader(),
            ),
            sorter: $sorter,
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new Filesystem(),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            cache: $cache,
        );

        $guard = new ModuleDependencyGuard($registry, $sorter);
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);
        $paths = new ModuleLifecyclePaths($config, $this->tempDir, $this->tempDir . '/app');
        $directoryOps = new ModuleDirectoryOperations(new Filesystem(), $paths);

        return new RemoveModuleUseCase($registry, $guard, $directoryOps, $invalidator);
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function createModule(string $name, bool $enabled = true, array $dependencies = []): void
    {
        $path = $this->tempDir . '/app/Modules/' . ucfirst($name);
        $this->filesystem->makeDirectory($path, 0755, true);

        $manifest = [
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'version' => '1.0.0'],
            'state' => ['enabled' => $enabled],
            'settings' => ['schema' => new \stdClass(), 'values' => new \stdClass()],
        ];
        if ($dependencies !== []) {
            $manifest['meta']['dependencies'] = $dependencies;
        }

        file_put_contents($path . '/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
