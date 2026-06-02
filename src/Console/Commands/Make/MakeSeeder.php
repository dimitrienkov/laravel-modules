<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Database\Console\Seeds\SeederMakeCommand;
use Illuminate\Support\Str;

/**
 * Module-aware `make:seeder`.
 *
 * The parent pins seeders to the `Database\Seeders` root namespace and the
 * `database/seeders` directory. In module mode the root namespace is swapped back
 * to the app namespace so the trait's module sub-namespace resolves cleanly, and
 * the file is redirected into the module's `Database/Seeders`.
 */
final class MakeSeeder extends SeederMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return 'Database\\Seeders';
    }

    protected function rootNamespace()
    {
        if (! $this->module() instanceof Module) {
            return parent::rootNamespace();
        }

        return $this->laravel->getNamespace();
    }

    protected function getPath($name)
    {
        $module = $this->module();

        if (! $module instanceof Module) {
            return parent::getPath($name);
        }

        $relative = Str::replaceFirst($this->moduleLayout()->seederNamespace($module) . '\\', '', $name);

        return $this->moduleLayout()->seedersDir($module) . '/' . str_replace('\\', '/', $relative) . '.php';
    }
}
