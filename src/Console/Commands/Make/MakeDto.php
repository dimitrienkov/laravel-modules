<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ArchitecturalGenerator;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Support\ModuleLayer;
use Illuminate\Console\GeneratorCommand;

final class MakeDto extends GeneratorCommand
{
    use ModuleAwareGenerator;
    use ArchitecturalGenerator {
        ArchitecturalGenerator::getDefaultNamespace insteadof ModuleAwareGenerator;
    }

    protected $name = 'make:dto';

    protected $description = 'Create a new data transfer object';

    protected $type = 'DTO';

    protected function moduleSubNamespace(): string
    {
        return ModuleLayer::Dtos->namespaceSegment();
    }

    protected function classSuffix(): string
    {
        return 'Dto';
    }

    protected function stubName(): string
    {
        return 'dto.stub';
    }
}
