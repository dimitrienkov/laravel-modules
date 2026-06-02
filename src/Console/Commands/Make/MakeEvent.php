<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use Illuminate\Foundation\Console\EventMakeCommand;

final class MakeEvent extends EventMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return 'Domain\\Events';
    }
}
