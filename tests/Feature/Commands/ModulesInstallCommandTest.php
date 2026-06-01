<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesInstallCommand;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesSourceArchive;
use DimitrienkoV\LaravelModules\Tests\Support\RegistersLifecycleCommands;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
final class ModulesInstallCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use CreatesSourceArchive;
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
    public function installFailsWhenSourceContainsStateFile(): void
    {
        $zipPath = $this->zipModuleSource(
            $this->tempDir . '/sources/blog.zip',
            $this->moduleManifestArray('blog'),
            ['state.json' => json_encode(['enabled' => true], JSON_THROW_ON_ERROR)],
        );

        $this->artisanCommand("modules:install {$zipPath}")
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
        return $this->zipModuleSource(
            $this->tempDir . '/sources/' . $name . '.zip',
            $this->moduleManifestArray($name),
        );
    }
}
