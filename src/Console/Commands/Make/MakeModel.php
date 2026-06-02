<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use Override;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Manifest\VO\Module;
use DimitrienkoV\LaravelModules\Support\ModuleSegment;
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
        return ModuleSegment::Models->namespaceSegment();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function buildFactoryReplacements(): array
    {
        $module = $this->module();

        if (! $module instanceof Module || (! (bool) $this->option('factory') && ! (bool) $this->option('all'))) {
            return parent::buildFactoryReplacements();
        }

        /** @var string $name */
        $name = $this->argument('name');
        $modelPath = Str::of($name)->studly()->replace('/', '\\')->toString();
        $factoryClass = '\\' . $this->moduleLayout()->factoriesNamespace($module) . '\\' . $modelPath . 'Factory';

        return [
            '{{ factory }}' => "/** @use HasFactory<{$factoryClass}> */\n    use HasFactory;",
            '{{ factoryImport }}' => 'use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;',
        ];
    }
}
