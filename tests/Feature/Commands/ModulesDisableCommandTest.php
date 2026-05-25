<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature\Commands;

use DimitrienkoV\LaravelModules\Application\Support\LifecycleRegistryInvalidator;
use DimitrienkoV\LaravelModules\Application\Support\ModuleDependencyGuard;
use DimitrienkoV\LaravelModules\Console\Commands\Modules\ModulesDisableCommand;
use DimitrienkoV\LaravelModules\Contracts\ModuleManifestRepositoryInterface;
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

final class ModulesDisableCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/disable_cmd_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/app/Modules', 0755, true);
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);

        $this->registerServices();
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

    private function registerServices(): void
    {
        $app = $this->app;
        $layout = new ModuleLayout();
        $validator = new ManifestValidator(new ManifestSettingsValidator());
        $config = new Repository([
            'modules' => ['paths' => ['directories' => ['app/Modules']]],
        ]);

        $manifests = new ModuleManifestRepository(
            layout: $layout,
            writer: new AtomicJsonWriter(),
            validator: $validator,
            namespaceResolver: new FakeNamespaceResolver($this->tempDir),
            documentReader: new ManifestDocumentReader(),
        );

        $sorter = new TopologicalSorter();
        $cache = new ModuleRegistryCache($validator, $layout, $this->tempDir);

        $registry = new ModuleRegistry(
            manifests: $manifests,
            sorter: $sorter,
            scanner: new ModuleDirectoryScanner(
                config: $config,
                filesystem: new Filesystem(),
                layout: $layout,
                basePath: $this->tempDir,
                appPath: $this->tempDir . '/app',
            ),
            cache: $cache,
        );

        $guard = new ModuleDependencyGuard($registry, $sorter);
        $invalidator = new LifecycleRegistryInvalidator($cache, $registry);

        $app->instance(ModuleRegistryInterface::class, $registry);
        $app->instance(ModuleRegistry::class, $registry);
        $app->instance(ModuleManifestRepositoryInterface::class, $manifests);
        $app->instance(ModuleDependencyGuard::class, $guard);
        $app->instance(LifecycleRegistryInvalidator::class, $invalidator);

        $app->make(Kernel::class)->registerCommand($app->make(ModulesDisableCommand::class));
    }

    /**
     * @param array<string, string> $dependencies
     */
    private function writeManifest(string $name, bool $enabled = true, array $dependencies = []): void
    {
        $studlyName = ucfirst($name);
        $path = $this->tempDir . '/app/Modules/' . $studlyName;
        mkdir($path, 0755, true);

        $manifest = [
            'meta' => [
                'name' => $name,
                'display_name' => $studlyName,
                'version' => '1.0.0',
            ],
            'state' => ['enabled' => $enabled],
            'settings' => ['schema' => [], 'values' => []],
        ];

        if ($dependencies !== []) {
            $manifest['meta']['dependencies'] = $dependencies;
        }

        file_put_contents($path . '/module.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    private function artisanCommand(string $command): PendingCommand
    {
        $result = $this->artisan($command);
        \assert($result instanceof PendingCommand);

        return $result;
    }
}
