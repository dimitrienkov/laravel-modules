<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\ServiceProviderLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceProviderLoader::class)]
#[Group('loaders')]
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
    public function registersModuleServiceProvidersByNamespaceAndFileName(): void
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
            $report = (new ServiceProviderLoader($app, new Filesystem(), new ModuleLayout()))
                ->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));
        } finally {
            spl_autoload_unregister($autoload);
        }

        self::assertNotNull($app->getProvider('App\\Modules\\Blog\\Providers\\BlogServiceProvider'));
        self::assertTrue($report->wasApplied());
        self::assertSame(['providers' => ['BlogServiceProvider']], $report->artifacts);
    }

    #[Test]
    public function appliesWithoutArtifactsWhenProvidersDirectoryIsMissing(): void
    {
        // §2.1: a missing Providers directory is not an absent precondition for
        // this loader — registering zero providers is a valid applied([]) outcome.
        $app = new Application($this->tempDir);

        $report = (new ServiceProviderLoader($app, new Filesystem(), new ModuleLayout()))
            ->load(ModuleFactory::make(path: $this->tempDir . '/Blog', namespace: 'App\\Modules\\Blog'));

        self::assertNull($app->getProvider('App\\Modules\\Blog\\Providers\\BlogServiceProvider'));
        self::assertSame(LoadStatus::Applied, $report->status);
        self::assertSame([], $report->artifacts);
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
