<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesEnableCommand;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesEnableCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/enable_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);

        $this->registerServices();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function enableSucceeds(): void
    {
        $this->writeManifest('blog', enabled: false);

        $this->artisanCommand('modules:enable blog')
            ->assertSuccessful()
            ->expectsOutputToContain('enabled');
    }

    #[Test]
    public function enableFailsWhenAlreadyEnabled(): void
    {
        $this->writeManifest('blog', enabled: true);

        $this->artisanCommand('modules:enable blog')
            ->assertFailed()
            ->expectsOutputToContain('already enabled');
    }

    #[Test]
    public function enableFailsWhenModuleNotFound(): void
    {
        $this->artisanCommand('modules:enable nonexistent')
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    #[Test]
    public function enableFailsWithMissingDependency(): void
    {
        $this->writeManifest('blog', enabled: false, dependencies: ['users' => '^1.0']);

        $this->artisanCommand('modules:enable blog')
            ->assertFailed()
            ->expectsOutputToContain('users');
    }

    private function registerServices(): void
    {
        $config = $this->lifecycleConfig();
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        $this->app->instance(ModuleRegistryInterface::class, $registry);
        $this->app->instance(ModuleRegistry::class, $registry);
        $this->app->instance(ModuleManifestRepositoryInterface::class, $manifests);
        $this->app->instance(ModuleStateRepositoryInterface::class, $stateRepo);
        $this->app->instance(ModuleDependencyGuard::class, $this->lifecycleDependencyGuard($registry));
        $this->app->instance(LifecycleRegistryInvalidator::class, $this->lifecycleInvalidator($cache, $registry));

        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModulesEnableCommand::class));
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function writeManifest(string $name, bool $enabled = true, array $dependencies = []): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, dependencies: $dependencies, schema: []);
        $this->writeModuleState($this->stateRoot, $name, $enabled);
    }

    private function artisanCommand(string $command): PendingCommand
    {
        $result = $this->artisan($command);
        \assert($result instanceof PendingCommand);

        return $result;
    }
}
