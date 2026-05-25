<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\MiddlewareLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MiddlewareLoaderTest extends TestCase
{
    use UsesTempDirectory;

    /** @var list<callable> */
    private array $autoloaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('middleware-loader');
    }

    protected function tearDown(): void
    {
        foreach ($this->autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->autoloaders = [];
        $this->deleteTempDirectory();

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

    #[Test]
    public function it_registers_middleware_from_two_modules_without_alias_collision(): void
    {
        $blogPath = $this->tempDir . '/Blog';
        $blogMiddlewareDir = $blogPath . '/Http/Middleware';
        mkdir($blogMiddlewareDir, 0755, true);
        file_put_contents(
            $blogMiddlewareDir . '/Throttle.php',
            '<?php namespace App\\Modules\\Blog\\Http\\Middleware; class Throttle { public function handle($request, $next) { return $next($request); } }',
        );
        $this->registerAutoloader($blogMiddlewareDir . '/Throttle.php', 'App\\Modules\\Blog\\Http\\Middleware\\Throttle');

        $shopPath = $this->tempDir . '/Shop';
        $shopMiddlewareDir = $shopPath . '/Http/Middleware';
        mkdir($shopMiddlewareDir, 0755, true);
        file_put_contents(
            $shopMiddlewareDir . '/Throttle.php',
            '<?php namespace App\\Modules\\Shop\\Http\\Middleware; class Throttle { public function handle($request, $next) { return $next($request); } }',
        );
        $this->registerAutoloader($shopMiddlewareDir . '/Throttle.php', 'App\\Modules\\Shop\\Http\\Middleware\\Throttle');

        $router = new Router(new Dispatcher());
        $loader = new MiddlewareLoader($router, new Filesystem(), new ModuleLayout());

        $loader->load(ModuleFactory::make(name: 'blog', path: $blogPath, namespace: 'App\\Modules\\Blog'));
        $loader->load(ModuleFactory::make(name: 'shop', path: $shopPath, namespace: 'App\\Modules\\Shop'));

        $middleware = $router->getMiddleware();
        self::assertArrayHasKey('blog.throttle', $middleware);
        self::assertSame('App\\Modules\\Blog\\Http\\Middleware\\Throttle', $middleware['blog.throttle']);
        self::assertArrayHasKey('shop.throttle', $middleware);
        self::assertSame('App\\Modules\\Shop\\Http\\Middleware\\Throttle', $middleware['shop.throttle']);
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
}
