<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ArchitecturalGenerator;
use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Support\ModuleLayer;
use Illuminate\Console\GeneratorCommand;

/**
 * `make:vo` deliberately enforces no suffix — value objects mirror the package's
 * own convention (`Version`, `Checksum`), so `make:vo Money` creates `Money`.
 */
final class MakeVo extends GeneratorCommand
{
    use ModuleAwareGenerator;
    use ArchitecturalGenerator {
        ArchitecturalGenerator::getDefaultNamespace insteadof ModuleAwareGenerator;
    }

    protected $name = 'make:vo';

    protected $description = 'Create a new value object';

    protected $type = 'Value object';

    protected function moduleSubNamespace(): string
    {
        return ModuleLayer::ValueObjects->namespaceSegment();
    }

    protected function classSuffix(): string
    {
        return '';
    }

    protected function stubName(): string
    {
        return 'vo.stub';
    }
}
