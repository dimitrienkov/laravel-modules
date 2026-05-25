<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ServiceProviderLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServiceProviderLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('provider-loader');
    }

    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function it_registers_module_service_providers_by_namespace_and_file_name(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        $providerPath = $modulePath . '/Providers';
        mkdir($providerPath, 0755, true);
        file_put_contents(
            $providerPath . '/BlogServiceProvider.php',
            '<?php namespace App\\Modules\\Blog\\Providers; class BlogServiceProvider extends \\Illuminate\\Support\\ServiceProvider {}',
        );
        $autoload = $this->registerAutoloader($providerPath . '/BlogServiceProvider.php');
        $app = new Application($this->tempDir);

        try {
            (new ServiceProviderLoader($app, new Filesystem(), new ModuleLayout()))
                ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));
        } finally {
            spl_autoload_unregister($autoload);
        }

        self::assertNotNull($app->getProvider('App\\Modules\\Blog\\Providers\\BlogServiceProvider'));
    }

    #[Test]
    public function it_returns_early_when_providers_directory_is_missing(): void
    {
        $app = new Application($this->tempDir);

        (new ServiceProviderLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog', namespace: 'App\\Modules\\Blog'));

        self::assertNull($app->getProvider('App\\Modules\\Blog\\Providers\\BlogServiceProvider'));
    }

    /**
     * @return callable(string): void
     */
    private function registerAutoloader(string $providerFile): callable
    {
        $autoload = static function (string $class) use ($providerFile): void {
            if ($class === 'App\\Modules\\Blog\\Providers\\BlogServiceProvider') {
                require $providerFile;
            }
        };

        spl_autoload_register($autoload);

        return $autoload;
    }
}
