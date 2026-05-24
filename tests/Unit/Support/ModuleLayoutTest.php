<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Tests\Unit\Support;

use DimitrienkoV\LaravelModules\Support\ModuleLayout;
use DimitrienkoV\LaravelModules\Tests\Support\ModuleFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleLayoutTest extends TestCase
{
    #[Test]
    public function it_resolves_module_subpaths_from_module_path(): void
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
    }
}
