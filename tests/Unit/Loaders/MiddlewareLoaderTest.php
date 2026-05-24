<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\MiddlewareLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MiddlewareLoaderTest extends TestCase
{
    private string $tempDir;

    /** @var list<callable> */
    private array $autoloaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-middleware-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->autoloaders = [];
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_registers_middleware_aliases_for_module(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $middlewareDir = $modulePath . '/Http/Middleware';
        mkdir($middlewareDir, 0755, true);
        file_put_contents(
            $middlewareDir . '/CheckAge.php',
            '<?php namespace App\\Modules\\Blog\\Http\\Middleware; class CheckAge { public function handle($request, $next) { return $next($request); } }',
        );
        $this->registerAutoloader($middlewareDir . '/CheckAge.php', 'App\\Modules\\Blog\\Http\\Middleware\\CheckAge');
        $router = new Router(new Dispatcher());

        (new MiddlewareLoader($router, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath, namespace: 'App\\Modules\\Blog'));

        $middleware = $router->getMiddleware();
        self::assertArrayHasKey('blog.check_age', $middleware);
        self::assertSame('App\\Modules\\Blog\\Http\\Middleware\\CheckAge', $middleware['blog.check_age']);
    }

    #[Test]
    public function it_skips_files_without_existing_class(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $middlewareDir = $modulePath . '/Http/Middleware';
        mkdir($middlewareDir, 0755, true);
        file_put_contents($middlewareDir . '/NonExistent.php', '<?php // empty file, class not autoloadable');
        $router = new Router(new Dispatcher());

        (new MiddlewareLoader($router, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(name: 'blog', path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame([], $router->getMiddleware());
    }

    #[Test]
    public function it_returns_early_when_middleware_directory_is_missing(): void
    {
        $router = new Router(new Dispatcher());

        (new MiddlewareLoader($router, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Missing'));

        self::assertSame([], $router->getMiddleware());
    }

    private function registerAutoloader(string $file, string $class): void
    {
        $autoloader = static function (string $requested) use ($file, $class): void {
            if ($requested === $class) {
                require_once $file;
            }
        };

        spl_autoload_register($autoloader);
        $this->autoloaders[] = $autoloader;
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
