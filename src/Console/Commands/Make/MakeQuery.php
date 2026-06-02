<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ArchitecturalGenerator;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use Illuminate\Console\GeneratorCommand;

final class MakeQuery extends GeneratorCommand
{
    use ModuleAwareGenerator;
    use ArchitecturalGenerator {
        ArchitecturalGenerator::getDefaultNamespace insteadof ModuleAwareGenerator;
    }

    protected $name = 'make:query';

    protected $description = 'Create a new query class';

    protected $type = 'Query';

    protected function moduleSubNamespace(): string
    {
        return 'Application\\Queries';
    }

    protected function classSuffix(): string
    {
        return 'Query';
    }

    protected function stubName(): string
    {
        return 'query.stub';
    }
}
