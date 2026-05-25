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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_resolves_module_factory_class_from_model_namespace(): void
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
    public function it_returns_early_when_factories_directory_is_missing(): void
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
    public function it_preserves_existing_host_factory_resolver_for_non_module_models(): void
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
