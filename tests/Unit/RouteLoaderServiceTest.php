<?php

namespace DimitrienkoV\LaravelModules\Tests\Unit;

use DimitrienkoV\LaravelModules\Enums\RouteTypeEnum;
use DimitrienkoV\LaravelModules\Services\RouteLoaderService;
use DimitrienkoV\LaravelModules\Tests\TestCase;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RouteRegistrar;
use Mockery;

class RouteLoaderServiceTest extends TestCase
{
    private RouteLoaderService $routeLoaderService;
    private Filesystem $mockFilesystem;
    private RouteRegistrar $mockRouteRegistrar;

    private Application $mockApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApplication = Mockery::mock(Application::class);
        $this->mockFilesystem = Mockery::mock(Filesystem::class);
        $this->mockRouteRegistrar = Mockery::mock(RouteRegistrar::class);
        $this->mockConfigRepository = Mockery::mock(Repository::class);

        $this->mockConfigData();

        $this->routeLoaderService = new RouteLoaderService(
            $this->mockApplication,
            $this->mockFilesystem,
            $this->mockRouteRegistrar,
            $this->mockConfigRepository
        );
    }

    public function testLoadRoutesForApiType(): void
    {
        $routeFiles = ['/app/Modules/UserModule/Routes/api.php'];

        $this->mockApplication
            ->shouldReceive('basePath')
            ->with('app/Modules/*/Routes/api.php')
            ->andReturnUsing(static function (string $arg): string {
                return 'path/to/' . $arg;
            })
            ->once();

        $this->mockFilesystem
            ->shouldReceive('glob')
            ->with('path/to/app/Modules/*/Routes/api.php')
            ->andReturn($routeFiles);

        $this->mockRouteRegistrar
            ->shouldReceive('prefix')
            ->with('api')
            ->andReturnSelf()
            ->once();

        $this->mockRouteRegistrar
            ->shouldReceive('middleware')
            ->with('api')
            ->andReturnSelf()
            ->once();

        $this->mockRouteRegistrar
            ->shouldReceive('group')
            ->with($routeFiles[0])
            ->once();

        $this->routeLoaderService->loadRoutes(RouteTypeEnum::API);
    }

    public function testLoadRoutesForWebType(): void
    {
        $routeFiles = ['/app/Modules/UserModule/Routes/web.php'];

        $this->mockApplication
            ->shouldReceive('basePath')
            ->with('app/Modules/*/Routes/web.php')
            ->andReturnUsing(static function (string $arg): string {
                return 'path/to/' . $arg;
            })
            ->once();

        $this->mockFilesystem
            ->shouldReceive('glob')
            ->with('path/to/app/Modules/*/Routes/web.php')
            ->andReturn($routeFiles);

        $this->mockRouteRegistrar
            ->shouldReceive('prefix')
            ->with(null)
            ->andReturnSelf()
            ->once();

        $this->mockRouteRegistrar
            ->shouldReceive('middleware')
            ->with(null)
            ->andReturnSelf()
            ->once();

        $this->mockRouteRegistrar
            ->shouldReceive('group')
            ->with($routeFiles[0])
            ->once();

        $this->routeLoaderService->loadRoutes(RouteTypeEnum::WEB);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
