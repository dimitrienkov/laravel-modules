<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\ModuleDirectoryPaths;
use DimitrienkoV\LaravelModules\Application\Support\ModuleSkeletonBuilder;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\MakeModuleCommand;
use DimitrienkoV\LaravelModules\Contracts\ManifestValidatorInterface;
use DimitrienkoV\LaravelModules\Contracts\NamespaceResolverInterface;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Support\AtomicFileWriter;
use DimitrienkoV\LaravelModules\Support\LocalFilesystem;
use DimitrienkoV\LaravelModules\Tests\Support\CreatesLifecycleEnvironment;
use DimitrienkoV\LaravelModules\Tests\Support\RegistersLifecycleCommands;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MakeModuleCommandTest extends TestCase
{
    use CreatesLifecycleEnvironment;
    use RegistersLifecycleCommands;

    private string $tempDir;

    private string $stateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/make_module_cmd_' . bin2hex(random_bytes(6));
        $this->stateRoot = $this->tempDir . '/storage/app/private/modules';
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);

        $this->registerMakeCommand();
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

        $this->registerMakeCommand();

        $this->artisanCommand('make:module blog')
            ->assertFailed()
            ->expectsOutputToContain('already exists');
    }

    #[Test]
    public function scaffoldsModuleWithDefaultKindInference(): void
    {
        $this->artisanCommand('make:module blog')
            ->assertSuccessful();

        $manifest = json_decode(
            file_get_contents($this->tempDir . '/app/Modules/Blog/module.json'),
            true,
        );

        $this->assertSame(1, $manifest['schema_version']);
        $this->assertSame('module', $manifest['meta']['kind']);
    }

    #[Test]
    public function scaffoldsModuleWithExplicitKind(): void
    {
        $this->artisanCommand('make:module blog --kind=integration')
            ->assertSuccessful();

        $manifest = json_decode(
            file_get_contents($this->tempDir . '/app/Modules/Blog/module.json'),
            true,
        );

        $this->assertSame('integration', $manifest['meta']['kind']);
    }

    #[Test]
    public function failsOnInvalidKind(): void
    {
        $this->artisanCommand('make:module blog --kind=invalid')
            ->assertFailed()
            ->expectsOutputToContain('allowed values: module, subsystem, integration');
    }

    #[Test]
    public function forceOverwritesExisting(): void
    {
        $this->artisanCommand('make:module blog')->assertSuccessful();

        $this->registerMakeCommand();

        $this->artisanCommand('make:module blog --overwrite')
            ->assertSuccessful();
    }

    private function registerMakeCommand(): void
    {
        $services = $this->registerCoreLifecycleServices();
        $this->app->instance(ManifestValidatorInterface::class, new ManifestValidator(new ManifestSettingsValidator()));
        $this->app->instance(NamespaceResolverInterface::class, $this->lifecycleNamespaceResolver());
        $this->app->instance(ModuleDirectoryPaths::class, $this->lifecycleDirectoryPaths($services['config']));
        $this->app->instance(ModuleSkeletonBuilder::class, new ModuleSkeletonBuilder(
            new LocalFilesystem(new Filesystem()),
            new AtomicFileWriter(),
            \dirname(__DIR__, 3) . '/stubs',
        ));
        $this->registerArtisanCommand(MakeModuleCommand::class);
    }
}
