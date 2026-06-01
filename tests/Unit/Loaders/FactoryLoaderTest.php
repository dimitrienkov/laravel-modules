<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\FactoryLoader;
use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use DimitrienkoV\LaravelModules\Tests\Support\UsesTempDirectory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FactoryLoader::class)]
#[Group('loaders')]
final class FactoryLoaderTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::flushState();
        $this->createTempDirectory('factory-loader');
    }

    protected function tearDown(): void
    {
        Factory::flushState();
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    #[Test]
    public function resolvesModuleFactoryClassFromModelNamespace(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Database/Factories', 0755, true);
        $loader = $this->loader();

        $loader->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame(
            'App\\Modules\\Blog\\Database\\Factories\\PostFactory',
            $loader->factoryClassFor('App\\Modules\\Blog\\Domain\\Models\\Post'),
        );
    }

    #[Test]
    public function returnsEarlyWhenFactoriesDirectoryIsMissing(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath, 0755, true);
        $loader = $this->loader();

        $loader->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame(
            'Database\\Factories\\PostFactory',
            $loader->factoryClassFor('App\\Models\\Post'),
        );
    }

    #[Test]
    public function preservesExistingHostFactoryResolverForNonModuleModels(): void
    {
        Factory::guessFactoryNamesUsing(
            static fn (string $modelClass): string => 'Host\\Database\\Factories\\'
                . basename(str_replace('\\', '/', $modelClass))
                . 'Factory',
        );

        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath . '/Database/Factories', 0755, true);
        $loader = $this->loader();

        $loader->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame(
            'App\\Modules\\Blog\\Database\\Factories\\PostFactory',
            Factory::resolveFactoryName('App\\Modules\\Blog\\Domain\\Models\\Post'),
        );
        self::assertSame(
            'Host\\Database\\Factories\\UserFactory',
            Factory::resolveFactoryName('App\\Models\\User'),
        );
    }

    private function loader(): FactoryLoader
    {
        return new FactoryLoader(new Application($this->tempDir), new Filesystem(), new ModuleLayout());
    }
}
