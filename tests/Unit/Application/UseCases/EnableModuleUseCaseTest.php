<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\UseCases\EnableModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyEnabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyDisabledException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyMissingException;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnableModuleUseCaseTest extends TestCase
{
    use CreatesLifecycleEnvironment;
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

        $state = $this->readStateFile($this->stateRoot, 'blog');
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
        $config = $this->lifecycleConfig();
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        $useCase = new EnableModuleUseCase(
            $registry,
            $stateRepo,
            $this->lifecycleDependencyGuard($registry),
            $this->lifecycleInvalidator($cache, $registry),
        );

        return [$useCase, $registry];
    }

    private function makeUseCase(): EnableModuleUseCase
    {
        return $this->makeUseCaseWithRegistry()[0];
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
}
