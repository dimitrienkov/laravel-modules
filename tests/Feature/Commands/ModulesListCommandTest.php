<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesListCommand;
use DimitrienkoV\LaravelModules\Contracts\ModuleRegistryInterface;
use DimitrienkoV\LaravelModules\Manifest\ManifestDocumentReader;
use DimitrienkoV\LaravelModules\Manifest\ManifestSettingsValidator;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Registry\ModuleDirectoryScanner;
use DimitrienkoV\LaravelModules\Registry\ModuleRegistryCache;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use DimitrienkoV\LaravelModules\Tests\Support\FakeNamespaceResolver;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesListCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/list_cmd_' . bin2hex(random_bytes(6));
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
        $this->registerServices();

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
        $this->registerServices();

        $this->artisanCommand('modules:list --enabled')
            ->assertSuccessful()
            ->expectsOutputToContain('blog');
    }

    #[Test]
    public function listFiltersByDisabled(): void
    {
        $this->writeManifest('blog', enabled: true);
        $this->writeManifest('users', enabled: false);
        $this->registerServices();

        $this->artisanCommand('modules:list --disabled')
            ->assertSuccessful()
            ->expectsOutputToContain('users');
    }

    #[Test]
    public function listShowsNoModulesMessage(): void
    {
        $this->registerServices();

        $this->artisanCommand('modules:list')
            ->assertSuccessful()
            ->expectsOutputToContain('No modules found');
    }

    private function registerServices(): void
    {
        $app = $this->app;
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules']]],
        ]);

        $registry = new ModuleRegistry(
            manifests: new ModuleManifestRepository(
                layout: $layout,
                writer: new AtomicJsonWriter(),
                validator: $validator,
                namespaceResolver: new FakeNamespaceResolver($this->tempDir),
                documentReader: new ManifestDocumentReader(),
            ),
            sorter: new TopologicalSorter(),
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new Filesystem(),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            cache: new ModuleRegistryCache($validator, $layout, $this->tempDir),
        );

        $app->instance(ModuleRegistryInterface::class, $registry);

        $app->make(Kernel::class)->registerCommand($app->make(ModulesListCommand::class));
    }

    private function writeManifest(string $name, bool $enabled = true): void
    {
        $studlyName = ucfirst($name);
        $path = $this->tempDir . '/app/Modules/' . $studlyName;
        mkdir($path, 0755, true);

        file_put_contents($path . '/module.json', json_encode([
            'meta' => [
                'name' => $name,
                'display_name' => $studlyName,
                'version' => '1.0.0',
            ],
            'state' => ['enabled' => $enabled],
            'settings' => ['schema' => [], 'values' => []],
        ], JSON_PRETTY_PRINT));
    }

    private function artisanCommand(string $command): PendingCommand
    {
        $result = $this->artisan($command);
        \assert($result instanceof PendingCommand);

        return $result;
    }
}
