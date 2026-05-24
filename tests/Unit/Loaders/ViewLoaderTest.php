<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ViewLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ViewLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-view-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_registers_view_namespace_for_module(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $viewsDir = $modulePath . '/Resources/views';
        mkdir($viewsDir, 0755, true);
        file_put_contents($viewsDir . '/index.blade.php', '<h1>Blog</h1>');
        $finder = new FileViewFinder(new Filesystem(), []);
        $factory = new ViewFactory(new EngineResolver(), $finder, new Dispatcher());

        (new ViewLoader($factory, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath));

        $hints = $finder->getHints();
        self::assertArrayHasKey('blog', $hints);
        self::assertSame([$viewsDir], $hints['blog']);
    }

    #[Test]
    public function it_returns_early_when_views_directory_is_missing(): void
    {
        $finder = new FileViewFinder(new Filesystem(), []);
        $factory = new ViewFactory(new EngineResolver(), $finder, new Dispatcher());

        (new ViewLoader($factory, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        self::assertSame([], $finder->getHints());
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
