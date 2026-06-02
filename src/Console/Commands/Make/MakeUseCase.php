<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ArchitecturalGenerator;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Support\ModuleLayer;
use Illuminate\Console\GeneratorCommand;

final class MakeUseCase extends GeneratorCommand
{
    use ModuleAwareGenerator;
    use ArchitecturalGenerator {
        ArchitecturalGenerator::getDefaultNamespace insteadof ModuleAwareGenerator;
    }

    protected $name = 'make:use-case';

    protected $description = 'Create a new use case class';

    protected $type = 'Use case';

    protected function moduleSubNamespace(): string
    {
        return ModuleLayer::UseCases->namespaceSegment();
    }

    protected function classSuffix(): string
    {
        return 'UseCase';
    }

    protected function stubName(): string
    {
        return 'use-case.stub';
    }
}
