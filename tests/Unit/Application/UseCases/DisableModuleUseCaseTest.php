<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Application\UseCases\DisableModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\DependentModulesExistException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyDisabledException;
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

final class DisableModuleUseCaseTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/disable_test_' . uniqid();
        $this->filesystem->makeDirectory($this->tempDir . '/bootstrap/cache', 0755, true);
        $this->filesystem->makeDirectory($this->tempDir . '/app/Modules', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function disablesEnabledModule(): void
    {
        $this->createModule('blog', enabled: true);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertFalse($result->isEnabled());
        $this->assertNotNull($result->state->updatedAt);

        $manifest = json_decode(
            file_get_contents($this->tempDir . '/app/Modules/Blog/module.json'),
            true,
        );
        $this->assertFalse($manifest['state']['enabled']);
    }

    #[Test]
    public function throwsWhenAlreadyDisabled(): void
    {
        $this->createModule('blog', enabled: false);
        $useCase = $this->makeUseCase();

        $this->expectException(ModuleAlreadyDisabledException::class);
        $useCase->execute('blog');
    }

    #[Test]
    public function throwsWhenEnabledDependentsExist(): void
    {
        $this->createModule('users', enabled: true);
        $this->createModule('blog', enabled: true, dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $this->expectException(DependentModulesExistException::class);
        $useCase->execute('users');
    }

    #[Test]
    public function allowsDisableWhenDependentIsDisabled(): void
    {
        $this->createModule('users', enabled: true);
        $this->createModule('blog', enabled: false, dependencies: ['users' => '^1.0']);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('users');

        $this->assertFalse($result->isEnabled());
    }

    #[Test]
    public function preservesInstalledAtTimestamp(): void
    {
        $installedAt = '2026-01-01T00:00:00+00:00';
        $this->createModule('blog', enabled: true, installedAt: $installedAt);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertSame($installedAt, $result->state->installedAt);
    }

    #[Test]
    public function invalidatesCacheAfterDisable(): void
    {
        $this->createModule('blog', enabled: true);
        $cachePath = $this->tempDir . '/bootstrap/cache/modules.php';

        [$useCase, $registry] = $this->makeUseCaseWithRegistry();
        $registry->all();

        file_put_contents($cachePath, '<?php return [];');
        $this->assertFileExists($cachePath);

        $useCase->execute('blog');

        $this->assertFileDoesNotExist($cachePath);
    }

    /**
     * @return array{0: DisableModuleUseCase, 1: ModuleRegistry}
     */
    private function makeUseCaseWithRegistry(): array
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules']]],
        ]);

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
            documentReader: new ManifestDocumentReader(),
        );

        $sorter = new TopologicalSorter();
        $cache = new ModuleRegistryCache($validator, $layout, $this->tempDir);

        $registry = new ModuleRegistry(
            manifests: $manifests,
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

        return [new DisableModuleUseCase($registry, $manifests, $guard, $invalidator), $registry];
    }

    private function makeUseCase(): DisableModuleUseCase
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules']]],
        ]);

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
            documentReader: new ManifestDocumentReader(),
        );

        $sorter = new TopologicalSorter();
        $cache = new ModuleRegistryCache($validator, $layout, $this->tempDir);

        $registry = new ModuleRegistry(
            manifests: $manifests,
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

        return new DisableModuleUseCase($registry, $manifests, $guard, $invalidator);
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
        $studlyName = ucfirst($name);
        $modulePath = $this->tempDir . '/app/Modules/' . $studlyName;
        $this->filesystem->makeDirectory($modulePath, 0755, true);

        $manifest = [
            'meta' => [
                'name' => $name,
                'display_name' => $studlyName,
                'version' => '1.0.0',
            ],
            'state' => [
                'enabled' => $enabled,
            ],
            'settings' => [
                'schema' => new \stdClass(),
                'values' => new \stdClass(),
            ],
        ];

        if ($dependencies !== []) {
            $manifest['meta']['dependencies'] = $dependencies;
        }
        if ($installedAt !== null) {
            $manifest['state']['installed_at'] = $installedAt;
        }

        file_put_contents(
            $modulePath . '/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }
}
