<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\BladeComponentLoader;
use DimitrienkoV\LaravelModules\Support\ContainerLifecycleHooks;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BladeComponentLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-blade-component-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_registers_blade_component_namespace_for_module(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/View/Components', 0755, true);
        $blade = new BladeCompiler(new Filesystem(), $this->tempDir);
        $app = new Application($this->tempDir);
        $app->singleton(BladeCompiler::class, static fn (): BladeCompiler => $blade);

        $this->loader($app)
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertFalse($app->resolved(BladeCompiler::class));

        $app->make(BladeCompiler::class);

        $namespaces = $blade->getClassComponentNamespaces();
        self::assertArrayHasKey('blog', $namespaces);
        self::assertSame('App\\Modules\\Blog\\View\\Components', $namespaces['blog']);
    }

    #[Test]
    public function it_registers_blade_component_namespace_when_compiler_was_already_resolved(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/View/Components', 0755, true);
        $blade = new BladeCompiler(new Filesystem(), $this->tempDir);
        $app = new Application($this->tempDir);
        $app->instance(BladeCompiler::class, $blade);
        $app->make(BladeCompiler::class);

        $this->loader($app)
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $namespaces = $blade->getClassComponentNamespaces();
        self::assertArrayHasKey('blog', $namespaces);
        self::assertSame('App\\Modules\\Blog\\View\\Components', $namespaces['blog']);
    }

    #[Test]
    public function it_returns_early_when_blade_components_directory_is_missing(): void
    {
        $blade = new BladeCompiler(new Filesystem(), $this->tempDir);
        $app = new Application($this->tempDir);
        $app->singleton(BladeCompiler::class, static fn (): BladeCompiler => $blade);

        $this->loader($app)
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        $app->make(BladeCompiler::class);

        self::assertSame([], $blade->getClassComponentNamespaces());
    }

    private function loader(Application $app): BladeComponentLoader
    {
        return new BladeComponentLoader(new ContainerLifecycleHooks($app), new Filesystem(), new ModuleLayout());
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
