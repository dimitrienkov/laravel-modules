<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class ModuleLayout
{
    public function bladeComponentsDir(Module $module): string
    {
        return $module->path . '/View/Components';
    }

    public function channelsFile(Module $module): string
    {
        return $module->path . '/Routes/channels.php';
    }

    public function commandsDir(Module $module): string
    {
        return $module->path . '/Console/Commands';
    }

    public function configDir(Module $module): string
    {
        return $module->path . '/Config';
    }

    public function consoleRoutesFile(Module $module): string
    {
        return $module->path . '/Routes/console.php';
    }

    public function factoriesDir(Module $module): string
    {
        return $module->path . '/Database/Factories';
    }

    public function langDir(Module $module): string
    {
        return $module->path . '/Lang';
    }

    public function manifestFile(Module $module): string
    {
        return $this->manifestFilePath($module->path);
    }

    public function manifestFilePath(string $modulePath): string
    {
        return rtrim($modulePath, '/\\') . '/module.json';
    }

    public function middlewareDir(Module $module): string
    {
        return $module->path . '/Http/Middleware';
    }

    public function migrationsDir(Module $module): string
    {
        return $module->path . '/Database/Migrations';
    }

    public function observersDir(Module $module): string
    {
        return $module->path . '/Domain/Observers';
    }

    public function policiesDir(Module $module): string
    {
        return $module->path . '/Domain/Policies';
    }

    public function providersDir(Module $module): string
    {
        return $module->path . '/Providers';
    }

    public function routeFile(Module $module, string $type): string
    {
        return $this->routesDir($module) . "/{$type}.php";
    }

    public function routesDir(Module $module): string
    {
        return $module->path . '/Routes';
    }

    public function viewsDir(Module $module): string
    {
        return $module->path . '/Resources/views';
    }
}
