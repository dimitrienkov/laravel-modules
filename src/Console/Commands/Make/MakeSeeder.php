<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use Override;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleSegment;
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
        return ModuleSegment::Seeders->namespaceSegment();
    }

    #[Override]
    protected function rootNamespace(): string
    {
        if (! $this->module() instanceof Module) {
            return parent::rootNamespace();
        }

        return $this->laravel->getNamespace();
    }

    #[Override]
    protected function getPath($name): string
    {
        $module = $this->module();

        if (! $module instanceof Module) {
            return parent::getPath($name);
        }

        $relative = Str::replaceFirst($this->moduleLayout()->seedersNamespace($module) . '\\', '', $name);

        return $this->moduleLayout()->classFilePath($this->moduleLayout()->seedersDir($module), $relative);
    }
}
