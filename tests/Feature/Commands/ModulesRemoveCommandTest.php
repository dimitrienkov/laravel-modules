<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryOperations;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesRemoveCommand;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\RegistersLifecycleCommands;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

#[Group('feature')]
final class ModulesRemoveCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use RegistersLifecycleCommands;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/remove_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        mkdir($this->tempDir . '/backups', 0755, true);

        $services = $this->registerCoreLifecycleServices(backupPath: $this->tempDir . '/backups');
        $this->app->instance(ModuleDirectoryOperations::class, $this->lifecycleDirectoryOps($this->lifecycleDirectoryPaths($services['config'])));
        $this->registerArtisanCommand(ModulesRemoveCommand::class);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function removeWithConfirmation(): void
    {
        $this->installModule('blog');

        $this->artisan('modules:remove', ['name' => 'blog', '--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('removed');
    }

    #[Test]
    public function removeFailsWhenModuleNotFound(): void
    {
        $this->artisan('modules:remove', ['name' => 'nonexistent', '--force' => true])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    #[Test]
    public function removeShowsBackupPath(): void
    {
        $this->installModule('blog');

        $this->artisan('modules:remove', ['name' => 'blog', '--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Backup');
    }

    #[Test]
    public function permanentRemoveShowsNoBackup(): void
    {
        $this->installModule('blog');

        $this->artisan('modules:remove', ['name' => 'blog', '--force' => true, '--delete-permanently' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('permanently deleted');
    }

    #[Test]
    public function removeSucceedsInTestEnvWithoutForce(): void
    {
        $this->installModule('blog');

        $this->artisan('modules:remove', ['name' => 'blog'])
            ->assertSuccessful()
            ->expectsOutputToContain('removed');
    }

    private function installModule(string $name): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, schema: new stdClass());
        $this->writeModuleState($this->stateRoot, $name, true, values: new stdClass());
    }
}
