<?php

namespace DimitrienkoV\LaravelModules\Tests\Unit;

use DimitrienkoV\LaravelModules\Services\ConfigLoaderService;
use DimitrienkoV\LaravelModules\Tests\TestCase;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Mockery;

class ConfigLoaderServiceTest extends TestCase
{
    private ConfigLoaderService $configLoaderService;

    private Filesystem $mockFilesystem;

    private Application $mockApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConfigRepository = Mockery::mock(Repository::class);
        $this->mockFilesystem = Mockery::mock(Filesystem::class);
        $this->mockApplication = Mockery::mock(Application::class);

        $this->mockConfigData();

        $this->configLoaderService = new ConfigLoaderService(
            $this->mockConfigRepository,
            $this->mockFilesystem,
            $this->mockApplication
        );
    }

    public function testLoadConfigs(): void
    {
        $configFile = 'app/Modules/UserModule/Config/test_config.php';
        $configData = ['key' => 'value'];

        $this->mockApplication
            ->shouldReceive('basePath')
            ->with('app/Modules/*/Config/*.php')
            ->andReturnUsing(static function (string $arg): string {
                return 'path/to/' . $arg;
            });

        $this->mockFilesystem
            ->shouldReceive('glob')
            ->with('path/to/app/Modules/*/Config/*.php')
            ->andReturn([$configFile]);

        $this->mockConfigRepository
            ->shouldReceive('set')
            ->with('test_config', $configData)
            ->once();

        $this->mockFilesystem
            ->shouldReceive('requireOnce')
            ->with($configFile)
            ->andReturn($configData);

        $this->configLoaderService->loadConfigs();
    }
}
