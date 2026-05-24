<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Feature;

use DimitrienkoV\LaravelModules\Console\Commands\ModulesOptimizeClearCommand;
use DimitrienkoV\LaravelModules\Console\Commands\ModulesOptimizeCommand;
use DimitrienkoV\LaravelModules\Manifest\ManifestValidator;
use DimitrienkoV\LaravelModules\Manifest\ModuleManifestRepository;
use DimitrienkoV\LaravelModules\Manifest\ModuleRegistry;
use DimitrienkoV\LaravelModules\Support\AtomicJsonWriter;
use DimitrienkoV\LaravelModules\Support\ComposerNamespaceResolver;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Support\TopologicalSorter;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ModulesOptimizeCommandTest extends TestCase
{
    private string $modulePath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-optimize-' . bin2hex(random_bytes(6));
        $this->modulePath = $this->tempDir . '/app/Modules/Blog';

        mkdir($this->modulePath, 0755, true);
        $this->writeComposer();
        $this->writeManifest();

        $app = $this->application();
        $app->instance(ModuleRegistry::class, $this->registry());
        $app->make(Kernel::class)->registerCommand($app->make(ModulesOptimizeCommand::class));
        $app->make(Kernel::class)->registerCommand($app->make(ModulesOptimizeClearCommand::class));
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function optimize_writes_v2_module_cache(): void
    {
        $this->artisanCommand('modules:optimize')->assertSuccessful();

        $cachePath = $this->tempDir . '/bootstrap/cache/modules.php';
        self::assertFileExists($cachePath);

        $payload = require $cachePath;

        self::assertSame(2, $payload['version']);
        self::assertSame(['blog'], $payload['load_order']);
        self::assertSame('App\\Modules\\Blog', $payload['modules']['blog']['namespace']);
    }

    #[Test]
    public function optimize_clear_removes_only_v2_module_cache(): void
    {
        mkdir($this->tempDir . '/bootstrap/cache', 0755, true);
        file_put_contents($this->tempDir . '/bootstrap/cache/modules.php', '<?php return [];');
        file_put_contents($this->tempDir . '/bootstrap/cache/modules-providers.php', '<?php return [];');

        $this->artisanCommand('modules:optimize-clear')->assertSuccessful();

        self::assertFileDoesNotExist($this->tempDir . '/bootstrap/cache/modules.php');
        self::assertFileExists($this->tempDir . '/bootstrap/cache/modules-providers.php');
    }

    private function registry(): ModuleRegistry
    {
        $layout = new ModuleLayout();
        $validator = new ManifestValidator();

        return new ModuleRegistry(
            config: new Repository([
                'modules' => [
                    'paths' => [
                        'directories' => ['app/Modules'],
                    ],
                ],
            ]),
            filesystem: new Filesystem(),
            manifests: new ModuleManifestRepository(
                layout: $layout,
                writer: new AtomicJsonWriter(),
                validator: $validator,
                namespaceResolver: new ComposerNamespaceResolver($this->tempDir),
            ),
            validator: $validator,
            sorter: new TopologicalSorter(),
            layout: $layout,
            basePath: $this->tempDir,
        );
    }

    private function application(): Application
    {
        if ($this->app === null) {
            self::fail('Testbench application is not initialized.');
        }

        return $this->app;
    }

    private function artisanCommand(string $command): PendingCommand
    {
        $pendingCommand = $this->artisan($command);

        if (! $pendingCommand instanceof PendingCommand) {
            self::fail("Command [{$command}] did not return a pending command instance.");
        }

        return $pendingCommand;
    }

    private function writeComposer(): void
    {
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode([
                'autoload' => [
                    'psr-4' => [
                        'App\\' => 'app/',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    private function writeManifest(): void
    {
        file_put_contents(
            $this->modulePath . '/module.json',
            json_encode([
                'meta' => [
                    'name' => 'blog',
                    'display_name' => 'Blog',
                    'version' => '1.0.0',
                    'dependencies' => [],
                ],
                'state' => [
                    'enabled' => true,
                ],
                'settings' => [
                    'schema' => [],
                    'values' => [],
                ],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
