<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\RouteLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouteLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/laravel-modules-route-loader-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    #[Test]
    public function it_registers_flat_and_versioned_route_files(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Routes/api', 0755, true);
        file_put_contents($modulePath . '/Routes/web.php', '<?php');
        file_put_contents($modulePath . '/Routes/api/v1.php', '<?php');
        $router = new RecordingRouter();

        (new RouteLoader($this->fakeApp(cached: false), $router, $this->config(), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertSame([
            [
                'attributes' => ['middleware' => ['web']],
                'routes' => $modulePath . '/Routes/web.php',
            ],
            [
                'attributes' => ['prefix' => 'api/v1', 'middleware' => ['api']],
                'routes' => $modulePath . '/Routes/api/v1.php',
            ],
        ], $router->groups);
    }

    #[Test]
    public function it_returns_early_when_routes_directory_is_missing(): void
    {
        $router = new RecordingRouter();

        (new RouteLoader($this->fakeApp(cached: false), $router, $this->config(), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog'));

        self::assertSame([], $router->groups);
    }

    #[Test]
    public function it_returns_early_when_routes_are_cached(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Routes', 0755, true);
        file_put_contents($modulePath . '/Routes/web.php', '<?php');
        $router = new RecordingRouter();

        (new RouteLoader($this->fakeApp(cached: true), $router, $this->config(), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertSame([], $router->groups);
    }

    private function fakeApp(bool $cached): Application
    {
        $app = $this->createMock(Application::class);
        $app->method('routesAreCached')->willReturn($cached);

        return $app;
    }

    private function config(): Repository
    {
        return new Repository([
            'modules' => [
                'routing' => [
                    'types' => [
                        'api' => [
                            'prefix' => 'api',
                            'middleware' => ['api'],
                        ],
                        'web' => [
                            'prefix' => null,
                            'middleware' => ['web'],
                        ],
                    ],
                ],
            ],
        ]);
    }

}

final class RecordingRouter extends Router
{
    /**
     * @var array<int, array{attributes: array<string, mixed>, routes: mixed}>
     */
    public array $groups = [];

    public function __construct()
    {
    }

    /**
     * @param array<string, mixed>         $attributes
     * @param \Closure|array<mixed>|string $routes
     *
     * @return $this
     */
    public function group(array $attributes, $routes): static
    {
        $this->groups[] = [
            'attributes' => $attributes,
            'routes' => $routes,
        ];

        return $this;
    }
}
