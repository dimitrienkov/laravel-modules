<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesEnableCommand;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesModuleFiles;
use DimitrienkoV\LaravelModules\Tests\Support\RegistersLifecycleCommands;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesEnableCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use CreatesModuleFiles;
    use RegistersLifecycleCommands;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/enable_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);

        $services = $this->registerCoreLifecycleServices();
        $this->app->instance(ModuleRegistry::class, $services['registry']);
        $this->registerArtisanCommand(ModulesEnableCommand::class);
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

    /**
     * @param array<string, string> $dependencies
     */
    private function writeManifest(string $name, bool $enabled = true, array $dependencies = []): void
    {
        $this->writeModuleManifest($this->tempDir . '/app/Modules', $name, dependencies: $dependencies, schema: []);
        $this->writeModuleState($this->stateRoot, $name, $enabled);
    }
}
