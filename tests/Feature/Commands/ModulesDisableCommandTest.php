<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesDisableCommand;
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

final class ModulesDisableCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/disable_cmd_' . bin2hex(random_bytes(6));
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
    public function disableSucceeds(): void
    {
        $this->writeManifest('blog', enabled: true);

        $this->artisanCommand('modules:disable blog')
            ->assertSuccessful()
            ->expectsOutputToContain('disabled');
    }

    #[Test]
    public function disableFailsWhenAlreadyDisabled(): void
    {
        $this->writeManifest('blog', enabled: false);

        $this->artisanCommand('modules:disable blog')
            ->assertFailed()
            ->expectsOutputToContain('already disabled');
    }

    #[Test]
    public function disableFailsWithEnabledDependents(): void
    {
        $this->writeManifest('users', enabled: true);
        $this->writeManifest('blog', enabled: true, dependencies: ['users' => '^1.0']);

        $this->artisanCommand('modules:disable users')
            ->assertFailed()
            ->expectsOutputToContain('blog');
    }

    #[Test]
    public function disableFailsWhenModuleNotFound(): void
    {
        $this->artisanCommand('modules:disable nonexistent')
            ->assertFailed()
            ->expectsOutputToContain('nonexistent');
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

        $this->app->make(Kernel::class)->registerCommand($this->app->make(ModulesDisableCommand::class));
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
