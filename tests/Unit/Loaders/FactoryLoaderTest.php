<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Loaders;

use DimitrienkoV\LaravelModules\Loaders\FactoryLoader;
use DimitrienkoV\LaravelModules\Loaders\VO\LoadStatus;
use DimitrienkoV\LaravelModules\Loaders\VO\SkipReason;
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
use ReflectionProperty;

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

        $report = $loader->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame(
            'App\\Modules\\Blog\\Database\\Factories\\PostFactory',
            $loader->factoryClassFor('App\\Modules\\Blog\\Domain\\Models\\Post'),
        );
        self::assertTrue($report->wasApplied());
        self::assertSame(['factories' => ['Database/Factories']], $report->artifacts);
    }

    #[Test]
    public function returnsEarlyWhenFactoriesDirectoryIsMissing(): void
    {
        $modulePath = $this->tempDir . '/Blog';
        mkdir($modulePath, 0755, true);
        $loader = $this->loader();

        $report = $loader->load(ModuleFactory::make(path: $modulePath, namespace: 'App\\Modules\\Blog'));

        self::assertSame(
            'Database\\Factories\\PostFactory',
            $loader->factoryClassFor('App\\Models\\Post'),
        );
        self::assertSame(LoadStatus::Skipped, $report->status);
        self::assertSame(SkipReason::NoDirectory, $report->reason);
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

    #[Test]
    public function laravelFactoryStillExposesStaticFactoryNameResolver(): void
    {
        // Tripwire: FactoryLoader reads Factory::$factoryNameResolver via reflection
        // to preserve the host's existing resolver when chaining module resolvers.
        // This is an intentional dependency on a Laravel internal — if the property
        // is renamed or removed (or made non-static), the loader would silently drop
        // the host resolver in production, so fail here on CI first.
        self::assertTrue(
            property_exists(Factory::class, 'factoryNameResolver'),
            'FactoryLoader depends on the internal Factory::$factoryNameResolver property.',
        );

        self::assertTrue(
            (new ReflectionProperty(Factory::class, 'factoryNameResolver'))->isStatic(),
            'FactoryLoader reads the resolver without an instance, so the property must stay static.',
        );
    }

    private function loader(): FactoryLoader
    {
        return new FactoryLoader(new Application($this->tempDir), new Filesystem(), new ModuleLayout());
    }
}
