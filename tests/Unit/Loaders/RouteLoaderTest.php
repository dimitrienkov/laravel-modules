<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\RouteLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Closure;

#[CoversClass(RouteLoader::class)]
#[Group('loaders')]
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
    public function registersConfigDrivenRouteFiles(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Routes', 0755, true);
        file_put_contents($modulePath . '/Routes/web.php', '<?php');
        file_put_contents($modulePath . '/Routes/api_v1.php', '<?php');
        $router = new RecordingRouter();

        $report = (new RouteLoader($this->fakeApp(cached: false), $router, $this->config(), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertSame([
            [
                'attributes' => ['middleware' => ['web']],
                'routes' => $modulePath . '/Routes/web.php',
            ],
            [
                'attributes' => ['prefix' => 'api/v1', 'middleware' => ['api_v1']],
                'routes' => $modulePath . '/Routes/api_v1.php',
            ],
        ], $router->groups);
        self::assertTrue($report->wasApplied());
        self::assertSame(['routes' => ['web.php', 'api_v1.php']], $report->artifacts);
    }

    #[Test]
    public function returnsEarlyWhenRoutesDirectoryIsMissing(): void
    {
        $router = new RecordingRouter();

        $report = (new RouteLoader($this->fakeApp(cached: false), $router, $this->config(), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog'));

        self::assertSame([], $router->groups);
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::NoDirectory, $report->reason);
    }

    #[Test]
    public function skipsWithEmptyDirectoryReasonWhenNoConfiguredRouteFilesExist(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Routes', 0755, true);
        // Routes/ exists but holds no web/api_v1 file the config knows about.
        file_put_contents($modulePath . '/Routes/console.php', '<?php');
        $router = new RecordingRouter();

        $report = (new RouteLoader($this->fakeApp(cached: false), $router, $this->config(), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertSame([], $router->groups);
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::EmptyDirectory, $report->reason);
    }

    #[Test]
    public function returnsEarlyWhenRoutesAreCached(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Routes', 0755, true);
        file_put_contents($modulePath . '/Routes/web.php', '<?php');
        $router = new RecordingRouter();

        $report = (new RouteLoader($this->fakeApp(cached: true), $router, $this->config(), new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $modulePath));

        self::assertSame([], $router->groups);
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::RoutesCached, $report->reason);
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
                        'web' => [
                            'prefix' => null,
                            'middleware' => ['web'],
                        ],
                        'api_v1' => [
                            'prefix' => 'api/v1',
                            'middleware' => ['api_v1'],
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

    public function __construct() {}

    /**
     * @param array<string, mixed>        $attributes
     * @param Closure|array<mixed>|string $routes
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
