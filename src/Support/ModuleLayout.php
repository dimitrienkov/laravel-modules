<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class ModuleLayout
{
    public function bladeComponentNamespace(Module $module): string
    {
        return $module->namespace . '\\View\\Components';
    }

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

    public function factoriesNamespace(Module $module): string
    {
        return $module->namespace . '\\Database\\Factories';
    }

    public function langDir(Module $module): string
    {
        return $module->path . '/Lang';
    }

    public function listenersDir(Module $module): string
    {
        return $module->path . '/Domain/Listeners';
    }

    public function manifestFile(Module $module): string
    {
        return $this->manifestFilePath($module->path);
    }

    public function manifestFilePath(string $modulePath): string
    {
        return rtrim($modulePath, '/\\') . '/' . ModuleFileNames::MANIFEST;
    }

    public function middlewareDir(Module $module): string
    {
        return $module->path . '/Http/Middleware';
    }

    public function middlewareNamespace(Module $module): string
    {
        return $module->namespace . '\\Http\\Middleware';
    }

    public function migrationsDir(Module $module): string
    {
        return $module->path . '/Database/Migrations';
    }

    public function modelNamespace(Module $module): string
    {
        return $module->namespace . '\\Domain\\Models';
    }

    public function observersNamespace(Module $module): string
    {
        return $module->namespace . '\\Domain\\Observers';
    }

    public function observersDir(Module $module): string
    {
        return $module->path . '/Domain/Observers';
    }

    public function policiesDir(Module $module): string
    {
        return $module->path . '/Domain/Policies';
    }

    public function policiesNamespace(Module $module): string
    {
        return $module->namespace . '\\Domain\\Policies';
    }

    public function providersDir(Module $module): string
    {
        return $module->path . '/Providers';
    }

    /**
     * An artifact directory expressed relative to its module root. Callers pass
     * absolute paths this same layout produced, so `$absolutePath` always sits
     * under `$module->path`; the `+ 1` drops the separator after the module root.
     */
    public function relativeToModule(Module $module, string $absolutePath): string
    {
        return substr($absolutePath, \strlen($module->path) + 1);
    }

    public function requestsNamespace(Module $module): string
    {
        return $module->namespace . '\\Http\\Requests';
    }

    public function routeFile(Module $module, string $type): string
    {
        return $this->routesDir($module) . "/{$type}.php";
    }

    public function routesDir(Module $module): string
    {
        return $module->path . '/Routes';
    }

    public function seedersNamespace(Module $module): string
    {
        return $module->namespace . '\\Database\\Seeders';
    }

    public function seedersDir(Module $module): string
    {
        return $module->path . '/Database/Seeders';
    }

    public function viewsDir(Module $module): string
    {
        return $module->path . '/Resources/views';
    }
}
