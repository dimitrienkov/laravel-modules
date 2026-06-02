<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Application\UseCases;

use DimitrienkoV\LaravelModules\Application\Enums\LifecycleOperation;
use DimitrienkoV\LaravelModules\Application\UseCases\DisableModuleUseCase;
use DimitrienkoV\LaravelModules\Contracts\ModuleDiagnosticsInterface;
use DimitrienkoV\LaravelModules\Exceptions\DependentModulesExistException;
use DimitrienkoV\LaravelModules\Exceptions\ModuleAlreadyDisabledException;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(DisableModuleUseCase::class)]
#[Group('lifecycle')]
final class DisableModuleUseCaseTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use MockeryPHPUnitIntegration;

    private string $tempDir;

    private string $stateRoot;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/disable_test_' . uniqid();
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
    public function disablesEnabledModule(): void
    {
        $this->createModule('blog', enabled: true);
        $useCase = $this->makeUseCase();

        $result = $useCase->execute('blog');

        $this->assertFalse($result->isEnabled());
        $this->assertNotNull($result->state->updatedAt);

        $state = $this->readStateFile($this->stateRoot, 'blog');
        $this->assertFalse($state['enabled']);
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

    #[Test]
    public function doesNotModifyModuleJson(): void
    {
        $this->createModule('blog', enabled: true);
        $manifestBefore = file_get_contents($this->tempDir . '/app/Modules/Blog/module.json');

        $this->makeUseCase()->execute('blog');

        $manifestAfter = file_get_contents($this->tempDir . '/app/Modules/Blog/module.json');
        $this->assertSame($manifestBefore, $manifestAfter);
    }

    #[Test]
    public function emitsStartedThenSucceededOnceOnTheHappyPath(): void
    {
        $this->createModule('blog', enabled: true);

        /** @var ModuleDiagnosticsInterface&Mockery\MockInterface $diagnostics */
        $diagnostics = Mockery::spy(ModuleDiagnosticsInterface::class);

        [$useCase] = $this->makeUseCaseWithRegistry($diagnostics);

        $useCase->execute('blog');

        $diagnostics->shouldHaveReceived('lifecycleStarted')->once()->with(LifecycleOperation::Disable, 'blog');
        $diagnostics->shouldHaveReceived('lifecycleSucceeded')->once()->with(LifecycleOperation::Disable, 'blog');
        $diagnostics->shouldNotHaveReceived('lifecycleFailed');
    }

    /**
     * @return array{0: DisableModuleUseCase, 1: ModuleRegistry}
     */
    private function makeUseCaseWithRegistry(?ModuleDiagnosticsInterface $diagnostics = null): array
    {
        $config = $this->lifecycleConfig();
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        $useCase = new DisableModuleUseCase(
            $registry,
            $stateRepo,
            $this->lifecycleDependencyGuard($registry),
            $this->lifecycleInvalidator($cache, $registry),
            $diagnostics ?? new NullModuleDiagnostics(),
        );

        return [$useCase, $registry];
    }

    private function makeUseCase(): DisableModuleUseCase
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
        $this->writeModuleState($this->stateRoot, $name, $enabled, $installedAt, new stdClass());
    }
}
