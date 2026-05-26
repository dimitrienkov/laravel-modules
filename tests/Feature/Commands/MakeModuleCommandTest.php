<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\MakeModuleCommand;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MakeModuleCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/make_module_cmd_' . bin2hex(random_bytes(6));
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
    public function scaffoldsModuleSuccessfully(): void
    {
        $this->artisanCommand('make:module blog')
            ->assertSuccessful()
            ->expectsOutputToContain('scaffolded');

        $this->assertDirectoryExists($this->tempDir . '/app/Modules/Blog');
        $this->assertFileExists($this->tempDir . '/app/Modules/Blog/module.json');
    }

    #[Test]
    public function scaffoldsDisabledModule(): void
    {
        $this->artisanCommand('make:module blog --disabled')
            ->assertSuccessful();

        $stateFile = $this->stateRoot . '/blog/state.json';
        $this->assertFileExists($stateFile);
        $state = json_decode(
            file_get_contents($stateFile),
            true,
        );
        $this->assertFalse($state['enabled']);
    }

    #[Test]
    public function failsOnInvalidName(): void
    {
        $this->artisanCommand('make:module "Invalid Name!"')
            ->assertFailed()
            ->expectsOutputToContain('invalid module name');
    }

    #[Test]
    public function failsWhenModuleAlreadyExists(): void
    {
        $this->artisanCommand('make:module blog')->assertSuccessful();

        $this->registerServices();

        $this->artisanCommand('make:module blog')
            ->assertFailed()
            ->expectsOutputToContain('already exists');
    }

    #[Test]
    public function forceOverwritesExisting(): void
    {
        $this->artisanCommand('make:module blog')->assertSuccessful();

        $this->registerServices();

        $this->artisanCommand('make:module blog --force')
            ->assertSuccessful();
    }

    private function registerServices(): void
    {
        $config = $this->lifecycleConfig();
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $cache = $this->lifecycleRegistryCache($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        $this->app->instance(ModuleRegistryInterface::class, $registry);
        $this->app->instance(ModuleManifestRepositoryInterface::class, $manifests);
        $this->app->instance(ModuleStateRepositoryInterface::class, $stateRepo);
        $this->app->instance(ManifestValidatorInterface::class, new ManifestValidator(new ManifestSettingsValidator()));
        $this->app->instance(NamespaceResolverInterface::class, $this->lifecycleNamespaceResolver());
        $this->app->instance(ModuleDirectoryPaths::class, $this->lifecycleDirectoryPaths($config));
        $this->app->instance(LifecycleRegistryInvalidator::class, $this->lifecycleInvalidator($cache, $registry));

        $this->app->make(Kernel::class)->registerCommand($this->app->make(MakeModuleCommand::class));
    }

    private function artisanCommand(string $command): PendingCommand
    {
        $result = $this->artisan($command);
        \assert($result instanceof PendingCommand);

        return $result;
    }
}
