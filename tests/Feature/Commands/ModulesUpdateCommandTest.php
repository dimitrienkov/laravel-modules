<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSourcePreparer;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesUpdateCommand;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\RegistersLifecycleCommands;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesUpdateCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use RegistersLifecycleCommands;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/update_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        mkdir($this->tempDir . '/sources', 0755, true);
        mkdir($this->tempDir . '/backups', 0755, true);

        $services = $this->registerCoreLifecycleServices(backupPath: $this->tempDir . '/backups');
        $this->app->instance(ModuleDirectoryOperations::class, $this->lifecycleDirectoryOps($this->lifecycleDirectoryPaths($services['config'])));
        $this->app->instance(ModuleSourcePreparer::class, $this->lifecycleSourcePreparer());
        $this->registerArtisanCommand(ModulesUpdateCommand::class);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function updateSucceeds(): void
    {
        $this->installModule('blog', '1.0.0');
        $sourceDir = $this->createSourceModule('blog', '2.0.0');

        $this->artisanCommand("modules:update blog {$sourceDir}")
            ->assertSuccessful()
            ->expectsOutputToContain('updated');
    }

    #[Test]
    public function updateFailsWhenModuleNotFound(): void
    {
        $sourceDir = $this->createSourceModule('blog', '2.0.0');

        $this->artisanCommand("modules:update nonexistent {$sourceDir}")
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    #[Test]
    public function updateOutputShowsVersionInfo(): void
    {
        $this->installModule('blog', '1.0.0');
        $sourceDir = $this->createSourceModule('blog', '2.0.0');

        $this->artisanCommand("modules:update blog {$sourceDir}")
            ->assertSuccessful()
            ->expectsOutputToContain('updated')
            ->expectsOutputToContain('Version');
    }

    private function installModule(string $name, string $version): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, $version, schema: new \stdClass());
        $this->writeModuleState($this->stateRoot, $name, true, values: new \stdClass());
    }

    private function createSourceModule(string $name, string $version): string
    {
        $dir = $this->tempDir . '/sources/' . ucfirst($name);
        if (is_dir($dir)) {
            (new Filesystem())->deleteDirectory($dir);
        }
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/module.json', json_encode([
            'meta' => ['name' => $name, 'display_name' => ucfirst($name), 'version' => $version],
            'settings' => ['schema' => new \stdClass()],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $dir;
    }
}
