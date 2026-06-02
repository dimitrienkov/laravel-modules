<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleLayout::class)]
#[Group('support')]
final class ModuleLayoutTest extends TestCase
{
    #[Test]
    public function resolvesModuleSubpathsFromModulePath(): void
    {
        $module = ModuleFactory::make(path: '/app/Modules/Blog');
        $layout = new ModuleLayout();

        self::assertSame('/app/Modules/Blog/module.json', $layout->manifestFile($module));
        self::assertSame('/app/Modules/Blog/Config', $layout->configDir($module));
        self::assertSame('/app/Modules/Blog/Routes', $layout->routesDir($module));
        self::assertSame('/app/Modules/Blog/Routes/web.php', $layout->routeFile($module, 'web'));
        self::assertSame('/app/Modules/Blog/Database/Migrations', $layout->migrationsDir($module));
        self::assertSame('/app/Modules/Blog/Database/Factories', $layout->factoriesDir($module));
        self::assertSame('/app/Modules/Blog/Providers', $layout->providersDir($module));
        self::assertSame('/app/Modules/Blog/Lang', $layout->langDir($module));
        self::assertSame('/app/Modules/Blog/Resources/views', $layout->viewsDir($module));
        self::assertSame('/app/Modules/Blog/View/Components', $layout->bladeComponentsDir($module));
        self::assertSame('/app/Modules/Blog/Console/Commands', $layout->commandsDir($module));
        self::assertSame('/app/Modules/Blog/Routes/console.php', $layout->consoleRoutesFile($module));
        self::assertSame('/app/Modules/Blog/Routes/channels.php', $layout->channelsFile($module));
        self::assertSame('/app/Modules/Blog/Domain/Observers', $layout->observersDir($module));
        self::assertSame('/app/Modules/Blog/Domain/Policies', $layout->policiesDir($module));
        self::assertSame('/app/Modules/Blog/Http/Middleware', $layout->middlewareDir($module));
        self::assertSame('/app/Modules/Blog/Domain/Listeners', $layout->listenersDir($module));
    }

    #[Test]
    public function relativeToModuleStripsTheModuleRootAndItsSeparator(): void
    {
        $module = ModuleFactory::make(path: '/app/Modules/Blog');
        $layout = new ModuleLayout();

        // The `+ 1` in the offset must drop the separator after the module root.
        self::assertSame(
            'Database/Migrations',
            $layout->relativeToModule($module, '/app/Modules/Blog/Database/Migrations'),
        );
        // Round-trips with the absolute paths this same layout produces.
        self::assertSame('Config', $layout->relativeToModule($module, $layout->configDir($module)));
        self::assertSame('Lang', $layout->relativeToModule($module, $layout->langDir($module)));
    }

    #[Test]
    public function resolvesNamespaceSegmentsFromModule(): void
    {
        $module = ModuleFactory::make(path: '/app/Modules/Blog', namespace: 'App\\Modules\\Blog');
        $layout = new ModuleLayout();

        self::assertSame('App\\Modules\\Blog\\View\\Components', $layout->bladeComponentNamespace($module));
        self::assertSame('App\\Modules\\Blog\\Database\\Factories', $layout->factoryNamespace($module));
        self::assertSame('App\\Modules\\Blog\\Http\\Middleware', $layout->middlewareNamespace($module));
        self::assertSame('App\\Modules\\Blog\\Domain\\Models', $layout->modelNamespace($module));
        self::assertSame('App\\Modules\\Blog\\Domain\\Observers', $layout->observerNamespace($module));
        self::assertSame('App\\Modules\\Blog\\Domain\\Policies', $layout->policyNamespace($module));
    }
}
