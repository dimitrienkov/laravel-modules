<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesInstallCommand;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\RegistersLifecycleCommands;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesInstallCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use RegistersLifecycleCommands;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/install_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        mkdir($this->tempDir . '/sources', 0755, true);

        $services = $this->registerCoreLifecycleServices(backupPath: $this->tempDir . '/backups');
        $paths = $this->lifecycleDirectoryPaths($services['config']);
        $this->app->instance(NamespaceResolverInterface::class, $this->lifecycleNamespaceResolver());
        $this->app->instance(ModuleDirectoryPaths::class, $paths);
        $this->app->instance(ModuleDirectoryOperations::class, $this->lifecycleDirectoryOps($paths));
        $this->app->instance(ModuleSourcePreparer::class, $this->lifecycleSourcePreparer());
        $this->registerArtisanCommand(ModulesInstallCommand::class);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function installFromDirectorySucceeds(): void
    {
        $sourceDir = $this->createSourceModule('blog');

        $this->artisanCommand("modules:install {$sourceDir}")
            ->assertSuccessful()
            ->expectsOutputToContain('installed');
    }

    #[Test]
    public function installFailsForNonexistentSource(): void
    {
        $this->artisanCommand('modules:install /nonexistent/path')
            ->assertFailed();
    }

    #[Test]
    public function installOutputShowsModuleDetails(): void
    {
        $sourceDir = $this->createSourceModule('blog');

        $this->artisanCommand("modules:install {$sourceDir}")
            ->assertSuccessful()
            ->expectsOutputToContain('blog')
            ->expectsOutputToContain('directory');
    }

    #[Test]
    public function installWithDisabledFlag(): void
    {
        $sourceDir = $this->createSourceModule('blog');

        $this->artisanCommand("modules:install {$sourceDir} --disabled")
            ->assertSuccessful()
            ->expectsOutputToContain('No');
    }

    #[Test]
    public function installWithDirectoryOptionToConfiguredRoot(): void
    {
        $sourceDir = $this->createSourceModule('blog');
        $configuredRoot = $this->tempDir . '/app/Modules';

        $this->artisanCommand("modules:install {$sourceDir} --directory={$configuredRoot}")
            ->assertSuccessful()
            ->expectsOutputToContain('installed');
    }

    private function createSourceModule(string $name): string
    {
        $dir = $this->tempDir . '/sources/' . ucfirst($name);
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/module.json', json_encode([
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'version' => '1.0.0'],
            'settings' => ['schema' => new \stdClass()],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $dir;
    }
}
