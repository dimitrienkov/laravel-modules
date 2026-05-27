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
    public function installFromZipSucceeds(): void
    {
        $zipPath = $this->createSourceZip('blog');

        $this->artisanCommand("modules:install {$zipPath}")
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
        $zipPath = $this->createSourceZip('blog');

        $this->artisanCommand("modules:install {$zipPath}")
            ->assertSuccessful()
            ->expectsOutputToContain('blog')
            ->expectsOutputToContain('zip');
    }

    #[Test]
    public function installWithDisabledFlag(): void
    {
        $zipPath = $this->createSourceZip('blog');

        $this->artisanCommand("modules:install {$zipPath} --disabled")
            ->assertSuccessful()
            ->expectsOutputToContain('No');
    }

    #[Test]
    public function installWithDirectoryOptionToConfiguredRoot(): void
    {
        $zipPath = $this->createSourceZip('blog');
        $configuredRoot = $this->tempDir . '/app/Modules';

        $this->artisanCommand("modules:install {$zipPath} --directory={$configuredRoot}")
            ->assertSuccessful()
            ->expectsOutputToContain('installed');
    }

    private function createSourceZip(string $name): string
    {
        $manifest = json_encode([
            'schema_version' => 1,
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'kind' => 'module', 'version' => '1.0.0'],
            'settings' => ['schema' => new \stdClass()],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $zipPath = $this->tempDir . '/sources/' . $name . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('module.json', $manifest);
        $zip->close();

        return $zipPath;
    }
}
