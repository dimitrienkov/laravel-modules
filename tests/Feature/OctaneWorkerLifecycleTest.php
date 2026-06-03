<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Contracts\FeatureRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Providers\ModuleLoaderServiceProvider;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Behavioural lock for the package's Octane worker-reuse contract.
 *
 * Under Octane a worker boots the provider once and reuses the container across
 * many requests. This suite resolves the *real* provider bindings (never a
 * hand-built object graph) and uses `forgetScopedInstances()` as the request
 * boundary — the same primitive Octane fires on `OperationTerminated`.
 *
 * The dual contract it pins:
 *   - feature VALUES are read fresh every request (scoped repository re-reads
 *     `state.json`);
 *   - the `enabled` flag and the discovered module set are FROZEN for the life
 *     of the worker (snapshot built once at boot), requiring `octane:reload`
 *     after mutations — by design, not a bug.
 */
#[Group('feature')]
final class OctaneWorkerLifecycleTest extends TestCase
{
    use CreatesModuleFiles;

    private string $tempDir;

    private string $modulesDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-octane-' . bin2hex(random_bytes(6));
        $this->modulesDir = $this->tempDir . '/app/Modules';
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';

        // Seed the on-disk module BEFORE the worker boots, so the provider's
        // boot-time snapshot is built from a populated discovery root.
        mkdir($this->modulesDir, 0755, true);

        // The namespace resolver derives the app namespace from composer.json at
        // the (relocated) base path, so the temp tree needs a minimal PSR-4 map.
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]], JSON_PRETTY_PRINT),
        );

        $this->writeModuleManifest($this->modulesDir, 'blog', schema: [
            'comments_enabled' => ['type' => 'bool', 'default' => false],
        ]);
        $this->writeModuleState($this->stateRoot, 'blog', enabled: true, values: ['comments_enabled' => false]);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function singletonRegistrySurvivesScopeReset(): void
    {
        $app = $this->application();

        $firstRequest = $app->make(ModuleRegistryInterface::class);
        $app->forgetScopedInstances();
        $secondRequest = $app->make(ModuleRegistryInterface::class);

        self::assertSame(
            $firstRequest,
            $secondRequest,
            'The module registry is a singleton and must outlive a per-request scope reset.',
        );
    }

    #[Test]
    public function featureValuesAreFreshWhileEnabledStaysFrozenWithinOneWorker(): void
    {
        $app = $this->application();
        $registry = $app->make(ModuleRegistryInterface::class);
        $registry->all();

        // First request observes the seeded state.
        $firstRequest = $app->make(FeatureRepositoryInterface::class);
        self::assertFalse($firstRequest->getBool('blog', 'comments_enabled'));
        self::assertTrue($registry->find('blog')->isEnabled());

        // A mutation lands on disk mid-worker: the value flips AND the module is
        // disabled. No `octane:reload` happens, so the worker keeps running.
        $this->writeModuleState($this->stateRoot, 'blog', enabled: false, values: ['comments_enabled' => true]);

        $app->forgetScopedInstances();
        $secondRequest = $app->make(FeatureRepositoryInterface::class);

        // VALUE is fresh: the scoped repository re-read state.json on the new scope.
        self::assertTrue(
            $secondRequest->getBool('blog', 'comments_enabled'),
            'Feature values must be read fresh on every request scope.',
        );

        // ENABLED is frozen: the singleton registry still serves the boot snapshot.
        self::assertTrue(
            $registry->find('blog')->isEnabled(),
            'The enabled flag is frozen for the worker lifetime until octane:reload.',
        );
    }

    #[Test]
    public function workerStateDoesNotGrowAcrossManyScopeResets(): void
    {
        $app = $this->application();
        $registry = $app->make(ModuleRegistryInterface::class);

        $baselineCount = \count($registry->all());

        $scopedInstances = [];
        for ($request = 0; $request < 5; $request++) {
            $app->forgetScopedInstances();
            $features = $app->make(FeatureRepositoryInterface::class);
            $features->getBool('blog', 'comments_enabled');
            $scopedInstances[] = $features;

            // The frozen surface never grows with request count.
            self::assertCount($baselineCount, $registry->all());
        }

        // Each request scope produced its own short-lived repository rather than
        // accumulating state on one long-lived object.
        self::assertCount(5, array_unique(array_map('spl_object_id', $scopedInstances)));
    }

    #[Test]
    public function backupRootIsFrozenForWorkerLifetime(): void
    {
        $app = $this->application();

        $paths = $app->make(ModuleDirectoryPaths::class);
        $bootBackupRoot = $paths->backupRoot();

        // A config mutation lands mid-worker without an `octane:reload`.
        $app['config']->set('modules.paths.backup', $this->tempDir . '/storage/app/other-backups');
        $app->forgetScopedInstances();

        // Like directories and state, the backup root is resolved once from the
        // shared ModulePathsConfig and frozen for the worker lifetime.
        self::assertSame(
            $bootBackupRoot,
            $app->make(ModuleDirectoryPaths::class)->backupRoot(),
            'The backup root is resolved once at boot and frozen until octane:reload.',
        );
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ModuleLoaderServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Move the worker's base path onto the temp tree so the real path
        // services discover the seeded module inside app_path().
        $app->setBasePath($this->tempDir);
        $app['config']->set('modules.paths.directories', ['app/Modules']);
        $app['config']->set('modules.paths.state', $this->stateRoot);
        $app['config']->set('modules.paths.backup', $this->tempDir . '/storage/app/module-backups');
    }

    private function application(): Application
    {
        if ($this->app === null) {
            self::fail('Testbench application is not initialized.');
        }

        return $this->app;
    }
}
