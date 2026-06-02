<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Console\Commands\Make;

use DimitrienkoV\LaravelModules\Console\Concerns\ModuleAwareGenerator;
use DimitrienkoV\LaravelModules\Support\ModuleSegment;
use Illuminate\Foundation\Console\PolicyMakeCommand;

final class MakePolicy extends PolicyMakeCommand
{
    use ModuleAwareGenerator;

    protected function moduleSubNamespace(): string
    {
        return ModuleSegment::Policies->namespaceSegment();
    }
}
