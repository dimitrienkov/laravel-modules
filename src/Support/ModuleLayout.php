<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Support;

use DimitrienkoV\LaravelModules\Manifest\VO\Module;

final readonly class ModuleLayout
{
    public function bladeComponentNamespace(Module $module): string
    {
        return $this->namespaceFor($module, ModuleSegment::Components);
    }

    public function bladeComponentsDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Components);
    }

    public function channelsFile(Module $module): string
    {
        return $module->path . '/Routes/channels.php';
    }

    /**
     * The absolute file path for a class living under `$directory`, derived from
     * its module-relative class name (sub-namespace separators become directory
     * separators). Shared by the path-overriding factory and seeder generators.
     */
    public function classFilePath(string $directory, string $relativeClass): string
    {
        return $directory . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    }

    public function commandsDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Commands);
    }

    public function configDir(Module $module): string
    {
        return $module->path . '/Config';
    }

    public function consoleRoutesFile(Module $module): string
    {
        return $module->path . '/Routes/console.php';
    }

    public function eventsNamespace(Module $module): string
    {
        return $this->namespaceFor($module, ModuleSegment::Events);
    }

    public function factoriesDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Factories);
    }

    public function factoriesNamespace(Module $module): string
    {
        return $this->namespaceFor($module, ModuleSegment::Factories);
    }

    public function langDir(Module $module): string
    {
        return $module->path . '/Lang';
    }

    public function listenersDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Listeners);
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
        return $this->directoryFor($module, ModuleSegment::Middleware);
    }

    public function middlewareNamespace(Module $module): string
    {
        return $this->namespaceFor($module, ModuleSegment::Middleware);
    }

    public function migrationsDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Migrations);
    }

    public function modelNamespace(Module $module): string
    {
        return $this->namespaceFor($module, ModuleSegment::Models);
    }

    public function observersNamespace(Module $module): string
    {
        return $this->namespaceFor($module, ModuleSegment::Observers);
    }

    public function observersDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Observers);
    }

    public function policiesDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Policies);
    }

    public function policiesNamespace(Module $module): string
    {
        return $this->namespaceFor($module, ModuleSegment::Policies);
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
        return $this->namespaceFor($module, ModuleSegment::Requests);
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
        return $this->namespaceFor($module, ModuleSegment::Seeders);
    }

    public function seedersDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Seeders);
    }

    public function viewsDir(Module $module): string
    {
        return $this->directoryFor($module, ModuleSegment::Views);
    }

    /**
     * Join the module's root namespace with a structural segment, e.g.
     * `App\Modules\Blog` + `Domain\Models` → `App\Modules\Blog\Domain\Models`.
     */
    private function namespaceFor(Module $module, ModuleSegment $segment): string
    {
        return $module->namespace . '\\' . $segment->namespaceSegment();
    }

    /**
     * Join the module's root path with a structural segment's relative directory,
     * e.g. `/app/Modules/Blog` + `Domain/Models` → `/app/Modules/Blog/Domain/Models`.
     */
    private function directoryFor(Module $module, ModuleSegment $segment): string
    {
        return $module->path . '/' . $segment->relativeDirectory();
    }
}
