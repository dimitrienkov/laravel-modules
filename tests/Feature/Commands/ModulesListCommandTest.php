<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\UseCases\ListModulesUseCase;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesListCommand;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\RegistersLifecycleCommands;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesListCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use RegistersLifecycleCommands;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/list_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function listShowsAllModules(): void
    {
        $this->writeManifest('blog', enabled: true);
        $this->writeManifest('users', enabled: false);
        $this->registerListCommand();

        $this->artisanCommand('modules:list')
            ->assertSuccessful()
            ->expectsOutputToContain('blog')
            ->expectsOutputToContain('users');
    }

    #[Test]
    public function listFiltersByEnabled(): void
    {
        $this->writeManifest('blog', enabled: true);
        $this->writeManifest('users', enabled: false);
        $this->registerListCommand();

        $this->artisanCommand('modules:list --enabled')
            ->assertSuccessful()
            ->expectsOutputToContain('blog')
            ->doesntExpectOutputToContain('users');
    }

    #[Test]
    public function listFiltersByDisabled(): void
    {
        $this->writeManifest('blog', enabled: true);
        $this->writeManifest('users', enabled: false);
        $this->registerListCommand();

        $this->artisanCommand('modules:list --disabled')
            ->assertSuccessful()
            ->expectsOutputToContain('users')
            ->doesntExpectOutputToContain('blog');
    }

    #[Test]
    public function listShowsNoModulesMessage(): void
    {
        $this->registerListCommand();

        $this->artisanCommand('modules:list')
            ->assertSuccessful()
            ->expectsOutputToContain('No modules found');
    }

    #[Test]
    public function conflictingFiltersReturnsError(): void
    {
        $this->registerListCommand();

        $this->artisanCommand('modules:list --enabled --disabled')
            ->assertFailed()
            ->expectsOutputToContain('cannot be used together');
    }

    private function registerListCommand(): void
    {
        $config = $this->lifecycleConfig();
        $stateRepo = $this->lifecycleStateRepository($config);
        $manifests = $this->lifecycleManifestRepository($stateRepo);
        $registry = $this->lifecycleRegistry($manifests, $stateRepo, $config);

        $this->app->instance(ModuleRegistryInterface::class, $registry);
        $this->app->instance(ListModulesUseCase::class, new ListModulesUseCase($registry));
        $this->registerArtisanCommand(ModulesListCommand::class);
    }

    private function writeManifest(string $name, bool $enabled = true): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, schema: []);
        $this->writeModuleState($this->stateRoot, $name, $enabled);
    }
}
