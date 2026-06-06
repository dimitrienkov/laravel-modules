<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\MoonShine;

use DimitrienkoV\LaravelModules\Application\Support\ModuleGroupLabelResolver;
use DimitrienkoV\LaravelModules\Application\UseCases\DisableModuleUseCase;
use DimitrienkoV\LaravelModules\Application\UseCases\EnableModuleUseCase;
use DimitrienkoV\LaravelModules\Application\UseCases\RemoveModuleUseCase;
use DimitrienkoV\LaravelModules\Exceptions\ModuleDependencyDisabledException;
use DimitrienkoV\LaravelModules\MoonShine\Pages\ModuleIndexPage;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleDependentsResolver;
use DimitrienkoV\LaravelModules\MoonShine\Support\ModuleKindLabelResolver;
use DimitrienkoV\LaravelModules\Support\Logging\NullModuleDiagnostics;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Support\Attributes\AsyncMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

/**
 * Covers the index page row actions that bypass the value-only resource save:
 * the async enable/disable Switcher routes to the lifecycle use cases, and the
 * remove action removes with the Backup strategy only. Uses a real lifecycle
 * environment so the wiring is proven end-to-end (the use cases are `final` and
 * cannot be mocked).
 */
#[Group('feature')]
final class ModuleIndexPageActionsTest extends TestCase
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
        $this->tempDir = sys_get_temp_dir() . '/index_actions_' . uniqid();
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
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
    public function toggleEnablesADisabledModuleThroughTheEnableUseCase(): void
    {
        $this->createModule('blog', enabled: false);
        $page = $this->makePage();

        $page->toggleEnabled($this->request('blog', enabled: true));

        self::assertTrue($this->readStateFile($this->stateRoot, 'blog')['enabled']);
    }

    #[Test]
    public function toggleDisablesAnEnabledModuleThroughTheDisableUseCase(): void
    {
        $this->createModule('blog', enabled: true);
        $page = $this->makePage();

        $page->toggleEnabled($this->request('blog', enabled: false));

        self::assertFalse($this->readStateFile($this->stateRoot, 'blog')['enabled']);
    }

    #[Test]
    public function toggleLetsUseCaseExceptionsPropagateForTheErrorToast(): void
    {
        // users is disabled, so enabling blog (which depends on it) must fail and
        // the exception surfaces to MoonShine instead of persisting a bad state.
        $this->createModule('users', enabled: false);
        $this->createModule('blog', enabled: false, dependencies: ['users' => '^1.0']);
        $page = $this->makePage();

        $this->expectException(ModuleDependencyDisabledException::class);

        $page->toggleEnabled($this->request('blog', enabled: true));
    }

    #[Test]
    public function removeUsesTheBackupStrategyOnly(): void
    {
        $this->createModule('blog', enabled: true);
        $page = $this->makePage();

        $page->removeModule($this->request('blog', enabled: false));

        self::assertDirectoryDoesNotExist($this->tempDir . '/app/Modules/Blog');
        self::assertNotEmpty($this->filesystem->directories($this->tempDir . '/backups'));
    }

    #[Test]
    public function toggleAndRemoveAreAsyncMethods(): void
    {
        // MoonShine's MethodController throws unless the target carries #[AsyncMethod].
        foreach (['toggleEnabled', 'removeModule'] as $method) {
            $attributes = (new ReflectionMethod(ModuleIndexPage::class, $method))
                ->getAttributes(AsyncMethod::class);

            self::assertCount(1, $attributes, "{$method} must be an #[AsyncMethod].");
        }
    }

    private function makePage(): ModuleIndexPage
    {
        $config = $this->lifecycleConfig(backupPath: $this->tempDir . '/backups');
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        return new ModuleIndexPage(
            $this->core(),
            new EnableModuleUseCase($registry, $stateRepo, $this->lifecycleDependencyGuard($registry), $this->lifecycleInvalidator($cache, $registry), new NullModuleDiagnostics()),
            new DisableModuleUseCase($registry, $stateRepo, $this->lifecycleDependencyGuard($registry), $this->lifecycleInvalidator($cache, $registry), new NullModuleDiagnostics()),
            new RemoveModuleUseCase($registry, $stateRepo, $this->lifecycleDependencyGuard($registry), $this->lifecycleDirectoryOps($this->lifecycleDirectoryPaths($config)), $this->lifecycleInvalidator($cache, $registry), new NullModuleDiagnostics()),
            new ModuleKindLabelResolver($this->translator()),
            new ModuleGroupLabelResolver($config),
            new ModuleDependentsResolver($registry),
            $this->translator(),
        );
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function createModule(string $name, bool $enabled = true, array $dependencies = []): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, dependencies: $dependencies);
        $this->writeModuleState($this->stateRoot, $name, $enabled, null, new stdClass());
    }

    private function request(string $name, bool $enabled): MoonShineRequest&Mockery\MockInterface
    {
        /** @var MoonShineRequest&Mockery\MockInterface $request */
        $request = Mockery::mock(MoonShineRequest::class);
        $request->shouldReceive('getItemID')->andReturn($name);
        $request->shouldReceive('boolean')->with('enabled')->andReturn($enabled);

        return $request;
    }

    private function core(): CoreContract&Mockery\MockInterface
    {
        /** @var CoreContract&Mockery\MockInterface */
        return Mockery::mock(CoreContract::class);
    }

    private function translator(): Translator&Mockery\MockInterface
    {
        /** @var Translator&Mockery\MockInterface $translator */
        $translator = Mockery::mock(Translator::class);
        $translator->shouldReceive('get')->andReturnUsing(static fn(string $key): string => $key);

        return $translator;
    }
}
