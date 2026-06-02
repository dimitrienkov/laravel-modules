<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ArchitecturalGenerator;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use Illuminate\Console\GeneratorCommand;

final class MakeAction extends GeneratorCommand
{
    use ModuleAwareGenerator;
    use ArchitecturalGenerator {
        ArchitecturalGenerator::getDefaultNamespace insteadof ModuleAwareGenerator;
    }

    protected $name = 'make:action';

    protected $description = 'Create a new action class';

    protected $type = 'Action';

    protected function moduleSubNamespace(): string
    {
        return 'Application\\Actions';
    }

    protected function classSuffix(): string
    {
        return 'Action';
    }

    protected function stubName(): string
    {
        return 'action.stub';
    }
}
