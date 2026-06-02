<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Support\Str;

/**
 * Module-aware `make:model`.
 *
 * The sub-generators it spawns (`--factory`, `--migration`, `--seed`,
 * `--controller`, `--requests`, `--policy`) are redirected into the module by the
 * trait's `call()` interception. The only content the parent hard-codes to the
 * host is the generated `HasFactory` annotation target, which we repoint at the
 * module factory namespace below.
 */
final class MakeModel extends ModelMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return 'Domain\\Models';
    }

    /**
     * @return array<string, string>
     */
    protected function buildFactoryReplacements()
    {
        $module = $this->module();

        if (! $module instanceof Module || (! $this->option('factory') && ! $this->option('all'))) {
            return parent::buildFactoryReplacements();
        }

        /** @var string $name */
        $name = $this->argument('name');
        $modelPath = Str::of($name)->studly()->replace('/', '\\')->toString();
        $factoryClass = '\\' . $this->moduleLayout()->factoryNamespace($module) . '\\' . $modelPath . 'Factory';

        return [
            '{{ factory }}' => "/** @use HasFactory<{$factoryClass}> */\n    use HasFactory;",
            '{{ factoryImport }}' => 'use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;',
        ];
    }
}
