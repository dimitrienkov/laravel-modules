<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesDisableCommand;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\RegistersLifecycleCommands;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesDisableCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use RegistersLifecycleCommands;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/disable_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);

        $services = $this->registerCoreLifecycleServices();
        $this->app->instance(ModuleRegistry::class, $services['registry']);
        $this->registerArtisanCommand(ModulesDisableCommand::class);
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

    /**
     * @param array<string, string> $dependencies
     */
    private function writeManifest(string $name, bool $enabled = true, array $dependencies = []): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, dependencies: $dependencies, schema: []);
        $this->writeModuleState($this->stateRoot, $name, $enabled);
    }
}
