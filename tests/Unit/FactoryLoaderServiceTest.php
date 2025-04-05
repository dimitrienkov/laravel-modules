<?php

namespace DimitrienkoV\LaravelModules\Tests\Unit;

use DimitrienkoV\LaravelModules\Services\FactoryLoaderService;
use DimitrienkoV\LaravelModules\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Mockery;

class FactoryLoaderServiceTest extends TestCase
{
    private FactoryLoaderService $factoryLoaderService;

    private const string MODEL = 'App\\Modules\\UserModule\\Models\\User';

    private const string FACTORY = 'App\\Modules\\UserModule\\Database\\Factories\\UserFactory';

    protected function setUp(): void
    {
        $this->mockConfigRepository = Mockery::mock(Repository::class);

        $this->mockConfigData();

        $this->registerClassAlias(self::FACTORY);
        $this->factoryLoaderService = new FactoryLoaderService($this->mockConfigRepository);
    }

    private function registerClassAlias(string $class): void
    {
        if (! class_exists($class)) {
            $parts = explode('\\', $class);
            $namespace = implode('\\', \array_slice($parts, 0, -1));
            $className = end($parts);

            eval('namespace ' . $namespace . '; class ' . $className . ' {}');
        }

        spl_autoload_register(static function ($className) use ($class) {
            class_alias($class, $className);
        });
    }

    public function testResolveFactoryName(): void
    {
        $this->factoryLoaderService->configureFactoryNameResolver();

        $resolvedFactory = Factory::resolveFactoryName(self::MODEL);
        $this->assertEquals(self::FACTORY, $resolvedFactory);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
